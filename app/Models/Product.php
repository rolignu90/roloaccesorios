<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'unit',
        'weight',
        'sale_price_without_vat',
        'wholesale_price_without_vat',
        'promo_type',
        'promo_value',
        'promo_active',
        'min_stock',
        'is_active',
        'free_shipping',
        'on_demand',
    ];

    public const PROMO_AMOUNT = 'amount';

    public const PROMO_PERCENT = 'percent';

    protected function casts(): array
    {
        return [
            'sale_price_without_vat' => 'decimal:2',
            'wholesale_price_without_vat' => 'decimal:2',
            'promo_value' => 'decimal:2',
            'promo_active' => 'boolean',
            'weight' => 'decimal:3',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
            'free_shipping' => 'boolean',
            'on_demand' => 'boolean',
        ];
    }

    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_suppliers')
            ->withPivot(['id', 'purchase_price', 'is_preferred'])
            ->withTimestamps();
    }

    public function preferredSupplier(): ?Supplier
    {
        return $this->suppliers()
            ->wherePivot('is_preferred', true)
            ->first()
            ?? $this->suppliers()->first();
    }

    public function inventoryLots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function productPriceTiers(): HasMany
    {
        return $this->hasMany(CustomerProductPriceTier::class);
    }

    public function hasSalesHistory(): bool
    {
        if ($this->saleItems()->exists()) {
            return true;
        }

        return $this->inventoryLots()
            ->whereHas('saleLotAllocations')
            ->exists();
    }

    public function availableLots(): HasMany
    {
        return $this->inventoryLots()
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id');
    }

    public function stockOnHand(): int
    {
        return (int) $this->inventoryLots()->sum('quantity_remaining');
    }

    public function isBelowMinStock(?int $stockOnHand = null): bool
    {
        if ($this->on_demand) {
            return false;
        }

        $min = (int) $this->min_stock;
        if ($min <= 0) {
            return false;
        }

        $stock = $stockOnHand;
        if ($stock === null) {
            $stock = array_key_exists('stock_on_hand', $this->attributes)
                ? (int) ($this->attributes['stock_on_hand'] ?? 0)
                : $this->stockOnHand();
        }

        return $stock <= $min;
    }

    public function averagePurchasePrice(): ?float
    {
        $prices = $this->productSuppliers
            ->pluck('purchase_price')
            ->map(fn ($price) => (float) $price);

        if ($prices->isEmpty()) {
            return null;
        }

        return round($prices->avg(), 2);
    }

    /** Estimated unit cost for on-demand shortfall (preferred supplier, average, or last lot). */
    public function estimatedUnitCost(): float
    {
        $this->loadMissing('productSuppliers');

        $preferred = $this->productSuppliers->firstWhere('is_preferred', true)
            ?? $this->productSuppliers->sortByDesc('id')->first();

        if ($preferred && $preferred->purchase_price !== null) {
            return round((float) $preferred->purchase_price, 2);
        }

        $avg = $this->averagePurchasePrice();
        if ($avg !== null) {
            return $avg;
        }

        $lastLot = $this->inventoryLots()
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->value('purchase_price');

        return $lastLot !== null ? round((float) $lastLot, 2) : 0.0;
    }

    public function salePriceWithVat(): float
    {
        return price_with_vat($this->sale_price_without_vat);
    }

    public function wholesalePriceWithVat(): ?float
    {
        if ($this->wholesale_price_without_vat === null) {
            return null;
        }

        return price_with_vat($this->wholesale_price_without_vat);
    }

    public function hasActivePromo(): bool
    {
        if (! $this->promo_active || $this->promo_value === null) {
            return false;
        }

        if (! in_array($this->promo_type, [self::PROMO_AMOUNT, self::PROMO_PERCENT], true)) {
            return false;
        }

        return (float) $this->promo_value > 0;
    }

    public function effectiveSalePriceWithVat(): float
    {
        $regular = $this->salePriceWithVat();

        if (! $this->hasActivePromo()) {
            return $regular;
        }

        if ($this->promo_type === self::PROMO_AMOUNT) {
            return round((float) $this->promo_value, 2);
        }

        $percent = min(100.0, max(0.0, (float) $this->promo_value));

        return round($regular * (1 - ($percent / 100)), 2);
    }

    public function promoBadgeLabel(): ?string
    {
        if (! $this->hasActivePromo()) {
            return null;
        }

        if ($this->promo_type === self::PROMO_AMOUNT) {
            return 'Promo '.money($this->promo_value).' c/IVA';
        }

        $percent = rtrim(rtrim(number_format((float) $this->promo_value, 2, '.', ''), '0'), '.');

        return "Promo -{$percent}%";
    }

    public function hasVaryingPurchasePrices(): bool
    {
        return $this->productSuppliers
            ->pluck('purchase_price')
            ->map(fn ($price) => number_format((float) $price, 2, '.', ''))
            ->unique()
            ->count() > 1;
    }

    public function syncSuppliers(array $rows): void
    {
        $this->productSuppliers()->delete();

        $normalized = collect($rows)
            ->filter(fn (array $row) => ! empty($row['supplier_id']))
            ->values();

        if ($normalized->isEmpty()) {
            return;
        }

        $preferredIndex = $normalized->search(fn (array $row) => ! empty($row['is_preferred']));
        if ($preferredIndex === false) {
            $preferredIndex = 0;
        }

        foreach ($normalized as $index => $row) {
            $this->productSuppliers()->create([
                'supplier_id' => $row['supplier_id'],
                'purchase_price' => $row['purchase_price'],
                'is_preferred' => $index === $preferredIndex,
            ]);
        }
    }
}
