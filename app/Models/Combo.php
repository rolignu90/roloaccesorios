<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Combo extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'sale_price_without_vat',
        'is_active',
        'free_shipping',
    ];

    protected function casts(): array
    {
        return [
            'sale_price_without_vat' => 'decimal:2',
            'is_active' => 'boolean',
            'free_shipping' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComboItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'combo_items')
            ->withPivot(['id', 'quantity'])
            ->withTimestamps();
    }

    public function salePriceWithVat(): float
    {
        return price_with_vat($this->sale_price_without_vat);
    }

    /**
     * Expand one combo sale into product lines with prices that sum to the combo price.
     *
     * @return list<array{product: Product, quantity: int, unit_price_with_vat: float, unit_price_without_vat: float, combo_id: int}>
     */
    public function expandToSaleLines(int $comboQuantity = 1): array
    {
        $comboQuantity = max(1, $comboQuantity);
        $this->loadMissing('items.product');

        $components = $this->items
            ->filter(fn (ComboItem $item) => $item->product && (int) $item->quantity > 0)
            ->values();

        if ($components->isEmpty()) {
            throw new \InvalidArgumentException("El combo {$this->code} no tiene productos.");
        }

        $comboPriceWithVat = $this->salePriceWithVat();
        $weights = $components->map(function (ComboItem $item) {
            $ref = $item->product->effectiveSalePriceWithVat();
            if ($ref <= 0) {
                $ref = 1.0;
            }

            return $ref * (int) $item->quantity;
        });
        $weightTotal = (float) $weights->sum();
        if ($weightTotal <= 0) {
            $weightTotal = (float) $components->count();
            $weights = $components->map(fn () => 1.0);
        }

        $lines = [];
        $allocated = 0.0;
        $lastIndex = $components->count() - 1;

        foreach ($components as $index => $item) {
            $componentQty = (int) $item->quantity;
            $lineQty = $componentQty * $comboQuantity;

            if ($index === $lastIndex) {
                $lineTotalWithVat = round(($comboPriceWithVat * $comboQuantity) - $allocated, 2);
            } else {
                $share = ((float) $weights[$index]) / $weightTotal;
                $lineTotalWithVat = round($comboPriceWithVat * $comboQuantity * $share, 2);
                $allocated = round($allocated + $lineTotalWithVat, 2);
            }

            $unitWithVat = $lineQty > 0
                ? round($lineTotalWithVat / $lineQty, 2)
                : 0.0;

            // Adjust unit so qty * unit matches line total (fix last unit cents if needed).
            $computedLine = round($unitWithVat * $lineQty, 2);
            if ($computedLine !== $lineTotalWithVat && $lineQty > 0) {
                $unitWithVat = round($lineTotalWithVat / $lineQty, 4);
            }

            $lines[] = [
                'product' => $item->product,
                'quantity' => $lineQty,
                'unit_price_with_vat' => round($unitWithVat, 2),
                'unit_price_without_vat' => price_without_vat(round($unitWithVat, 2)),
                'combo_id' => $this->id,
                'free_shipping' => (bool) $this->free_shipping,
            ];
        }

        // Final penny fix on last line unit if sum drifts.
        $sum = round(collect($lines)->sum(
            fn (array $line) => round($line['unit_price_with_vat'] * $line['quantity'], 2)
        ), 2);
        $target = round($comboPriceWithVat * $comboQuantity, 2);
        $diff = round($target - $sum, 2);
        if ($diff !== 0.0 && $lines !== []) {
            $last = count($lines) - 1;
            $qty = $lines[$last]['quantity'];
            if ($qty > 0) {
                $newLineTotal = round(($lines[$last]['unit_price_with_vat'] * $qty) + $diff, 2);
                $lines[$last]['unit_price_with_vat'] = round($newLineTotal / $qty, 2);
                $lines[$last]['unit_price_without_vat'] = price_without_vat($lines[$last]['unit_price_with_vat']);
            }
        }

        return $lines;
    }

    public function availableComboStock(): int
    {
        $this->loadMissing('items.product');

        $limits = $this->items->map(function (ComboItem $item) {
            if ($item->product?->on_demand) {
                return null;
            }

            $need = max(1, (int) $item->quantity);
            $stock = (int) ($item->product?->stock_on_hand
                ?? $item->product?->stockOnHand()
                ?? 0);

            return (int) floor($stock / $need);
        });

        $finite = $limits->filter(fn ($value) => $value !== null);
        if ($finite->isEmpty()) {
            // All components are on-demand: treat as always available.
            return 9999;
        }

        return (int) $finite->min();
    }

    /**
     * Payload for sale-create JS.
     */
    public function toSaleCatalogEntry(): array
    {
        $this->loadMissing('items.product');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'price' => $this->salePriceWithVat(),
            'stock' => $this->availableComboStock(),
            'on_demand' => $this->items->every(fn (ComboItem $item) => (bool) $item->product?->on_demand),
            'free_shipping' => (bool) $this->free_shipping,
            'items' => $this->items->map(function (ComboItem $item) {
                $product = $item->product;

                return [
                    'product_id' => $item->product_id,
                    'quantity' => (int) $item->quantity,
                    'code' => $product?->code,
                    'name' => $product?->name,
                    'ref_price' => $product ? $product->effectiveSalePriceWithVat() : 0,
                    'stock' => (int) ($product?->stock_on_hand ?? $product?->stockOnHand() ?? 0),
                    'on_demand' => (bool) ($product?->on_demand),
                    'free_shipping' => (bool) $this->free_shipping || (bool) ($product?->free_shipping),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, ComboItem>|array  $rows
     */
    public function syncItems(array $rows): void
    {
        $this->items()->delete();

        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $this->items()->create([
                'product_id' => $productId,
                'quantity' => $quantity,
            ]);
        }
    }

    public function duplicate(): self
    {
        $this->loadMissing('items');

        return DB::transaction(function () {
            $copy = static::query()->create([
                'code' => $this->nextDuplicateCode(),
                'name' => $this->nextDuplicateName(),
                'description' => $this->description,
                'sale_price_without_vat' => $this->sale_price_without_vat,
                'is_active' => true,
                'free_shipping' => (bool) $this->free_shipping,
            ]);

            $copy->syncItems(
                $this->items->map(fn (ComboItem $item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ])->all()
            );

            return $copy->fresh(['items.product']);
        });
    }

    private function nextDuplicateCode(): string
    {
        $base = strtoupper(trim((string) $this->code));
        $base = preg_replace('/-COPIA(-\d+)?$/', '', $base) ?: $base;
        $candidate = $base.'-COPIA';
        $n = 2;

        while (static::query()->where('code', $candidate)->exists()) {
            $candidate = $base.'-COPIA-'.$n;
            $n++;
        }

        return $candidate;
    }

    private function nextDuplicateName(): string
    {
        $base = trim((string) $this->name);
        $base = preg_replace('/\s*\(copia( \d+)?\)$/i', '', $base) ?: $base;
        $candidate = $base.' (copia)';
        $n = 2;

        while (static::query()->where('name', $candidate)->exists()) {
            $candidate = $base.' (copia '.$n.')';
            $n++;
        }

        return $candidate;
    }
}
