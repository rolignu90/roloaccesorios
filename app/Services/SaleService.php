<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleLotAllocation;
use App\Models\Seller;
use App\Models\ShippingCarrier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SaleService
{
    public function __construct(private FifoInventoryService $fifo)
    {
    }

    public function create(array $data): Sale
    {
        $items = collect($data['items'] ?? [])
            ->filter(fn (array $item) => ! empty($item['product_id']) && (int) ($item['quantity'] ?? 0) > 0)
            ->values();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException('La venta debe tener al menos un producto.');
        }

        return DB::transaction(function () use ($data, $items) {
            $vatRate = (float) ($data['vat_rate'] ?? config('sales.vat_rate', 0.13));
            $computedItems = [];
            $subtotal = 0.0;

            foreach ($items as $item) {
                $product = Product::query()->lockForUpdate()->findOrFail($item['product_id']);
                $quantity = (int) $item['quantity'];
                $unitPrice = (float) ($item['unit_price_without_vat'] ?? $product->sale_price_without_vat);
                $unitPriceWithVat = isset($item['unit_price_with_vat'])
                    ? round((float) $item['unit_price_with_vat'], 2)
                    : price_with_vat($unitPrice);
                $lineDiscountPercent = (float) ($item['discount_percent'] ?? 0);
                // Monto de descuento se captura en USD con IVA (mismo criterio que el precio).
                $lineDiscountAmountWithVat = (float) ($item['discount_amount'] ?? 0);

                $grossWithVat = round($quantity * $unitPriceWithVat, 2);
                $percentDiscountWithVat = round($grossWithVat * ($lineDiscountPercent / 100), 2);
                $lineDiscountWithVat = min($grossWithVat, round($percentDiscountWithVat + $lineDiscountAmountWithVat, 2));
                $netWithVat = round($grossWithVat - $lineDiscountWithVat, 2);
                $lineSubtotal = round($netWithVat / (1 + $vatRate), 2);

                $computedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price_without_vat' => $unitPrice,
                    'discount_percent' => $lineDiscountPercent,
                    'discount_amount' => $lineDiscountAmountWithVat,
                    'line_subtotal' => $lineSubtotal,
                    'line_net_with_vat' => $netWithVat,
                ];

                $subtotal += $lineSubtotal;
            }

            $subtotal = round($subtotal, 2);
            $subtotalWithVat = round(collect($computedItems)->sum('line_net_with_vat'), 2);
            $globalDiscountPercent = (float) ($data['discount_percent'] ?? 0);
            // Monto global también en USD con IVA.
            $globalDiscountAmountWithVat = (float) ($data['discount_amount'] ?? 0);
            $percentGlobalWithVat = round($subtotalWithVat * ($globalDiscountPercent / 100), 2);
            $discountTotalWithVat = min($subtotalWithVat, round($percentGlobalWithVat + $globalDiscountAmountWithVat, 2));
            $netWithVat = round($subtotalWithVat - $discountTotalWithVat, 2);
            $taxableBase = round($netWithVat / (1 + $vatRate), 2);
            $vatAmount = round($netWithVat - $taxableBase, 2);
            $hasShipping = array_key_exists('has_shipping', $data)
                ? (bool) $data['has_shipping']
                : true;

            $shippingAmount = 0.0;
            if ($hasShipping) {
                $shippingAmount = round((float) ($data['shipping_amount'] ?? config('sales.default_shipping_amount', 3)), 2);
                if ($shippingAmount < 0) {
                    $shippingAmount = 0;
                }

                $hasFreeShippingProduct = collect($computedItems)
                    ->contains(fn (array $computed) => (bool) $computed['product']->free_shipping);

                if ($hasFreeShippingProduct) {
                    $shippingAmount = 0.0;
                }
            }

            $total = round($netWithVat + $shippingAmount, 2);

            $carrierId = null;
            $carrierShippingCost = 0.0;
            $carrierCommission = 0.0;

            if ($hasShipping) {
                $carrierId = ! empty($data['shipping_carrier_id']) ? (int) $data['shipping_carrier_id'] : null;
                if ($carrierId) {
                    $carrier = ShippingCarrier::query()
                        ->where('is_active', true)
                        ->findOrFail($carrierId);
                    $costs = $carrier->costsForTotal($total);
                    $carrierShippingCost = $costs['shipping_cost'];
                    $carrierCommission = $costs['commission_amount'];
                }
            }

            $seller = Seller::query()->findOrFail($data['seller_id']);

            $sale = Sale::query()->create([
                'number' => $data['number'] ?? $this->nextNumber($seller),
                'customer_id' => $data['customer_id'],
                'seller_id' => $seller->id,
                'sold_at' => $data['sold_at'] ?? now(),
                'status' => Sale::STATUS_CONFIRMED,
                'subtotal_without_vat' => $subtotal,
                'discount_percent' => $globalDiscountPercent,
                'discount_amount' => $globalDiscountAmountWithVat,
                'taxable_base' => $taxableBase,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'shipping_amount' => $shippingAmount,
                'has_shipping' => $hasShipping,
                'shipping_carrier_id' => $carrierId,
                'carrier_shipping_cost' => $carrierShippingCost,
                'carrier_commission_amount' => $carrierCommission,
                'total' => $total,
                'cogs_total' => 0,
                'gross_margin' => 0,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'notes' => $data['notes'] ?? null,
            ]);

            $saleCogs = 0.0;
            $discountRatio = $subtotal > 0 ? ($taxableBase / $subtotal) : 1;

            foreach ($computedItems as $computed) {
                $allocatedSubtotal = round($computed['line_subtotal'] * $discountRatio, 2);
                $lineVat = round($allocatedSubtotal * $vatRate, 2);
                $lineTotal = round($allocatedSubtotal + $lineVat, 2);

                $allocations = $this->fifo->consume($computed['product'], $computed['quantity'], [
                    'type' => 'sale',
                    'id' => $sale->id,
                    'occurred_at' => $sale->sold_at,
                    'notes' => 'Salida por venta '.$sale->number,
                ]);
                $itemCogs = 0.0;

                $saleItem = SaleItem::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $computed['product']->id,
                    'quantity' => $computed['quantity'],
                    'unit_price_without_vat' => $computed['unit_price_without_vat'],
                    'discount_percent' => $computed['discount_percent'],
                    'discount_amount' => $computed['discount_amount'],
                    'line_subtotal' => $allocatedSubtotal,
                    'line_vat' => $lineVat,
                    'line_total' => $lineTotal,
                    'cogs_total' => 0,
                ]);

                foreach ($allocations as $allocation) {
                    $cogsAmount = round(((float) $allocation['purchase_price']) * $allocation['quantity'], 2);
                    $itemCogs += $cogsAmount;

                    SaleLotAllocation::query()->create([
                        'sale_item_id' => $saleItem->id,
                        'inventory_lot_id' => $allocation['lot_id'],
                        'quantity' => $allocation['quantity'],
                        'purchase_price' => $allocation['purchase_price'],
                        'cogs_amount' => $cogsAmount,
                    ]);
                }

                $saleItem->update(['cogs_total' => round($itemCogs, 2)]);
                $saleCogs += $itemCogs;
            }

            $saleCogs = round($saleCogs, 2);
            $sale->update([
                'cogs_total' => $saleCogs,
                'gross_margin' => round($taxableBase - $saleCogs, 2),
            ]);

            return $sale->fresh(['customer', 'seller', 'items.product', 'items.lotAllocations.inventoryLot']);
        });
    }

    public function void(Sale $sale): Sale
    {
        if ($sale->isVoided()) {
            throw new RuntimeException('La venta ya está anulada.');
        }

        return DB::transaction(function () use ($sale) {
            $sale->load('items.lotAllocations');

            $restore = [];
            foreach ($sale->items as $item) {
                foreach ($item->lotAllocations as $allocation) {
                    $restore[] = [
                        'lot_id' => $allocation->inventory_lot_id,
                        'quantity' => $allocation->quantity,
                    ];
                }
            }

            $this->fifo->restore($restore, [
                'type' => 'sale',
                'id' => $sale->id,
                'occurred_at' => now(),
                'notes' => 'Restauración por anulación '.$sale->number,
            ]);

            $sale->update([
                'status' => Sale::STATUS_VOIDED,
                'voided_at' => now(),
            ]);

            return $sale->fresh(['customer', 'seller', 'items.product', 'items.lotAllocations.inventoryLot']);
        });
    }

    public function addItem(Sale $sale, array $data): Sale
    {
        $this->assertConfirmed($sale);

        return DB::transaction(function () use ($sale, $data) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $vatRate = (float) $sale->vat_rate;
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            $quantity = (int) $data['quantity'];
            $unitPriceWithVat = round((float) $data['unit_price_with_vat'], 2);
            $unitPrice = round($unitPriceWithVat / (1 + $vatRate), 4);
            $discountPercent = (float) ($data['discount_percent'] ?? 0);
            $discountAmount = (float) ($data['discount_amount'] ?? 0);

            $allocations = $this->fifo->consume($product, $quantity, [
                'type' => 'sale',
                'id' => $sale->id,
                'occurred_at' => now(),
                'notes' => 'Ajuste venta '.$sale->number.' (agregar ítem)',
            ]);

            $itemCogs = 0.0;
            $saleItem = SaleItem::query()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price_without_vat' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'line_subtotal' => 0,
                'line_vat' => 0,
                'line_total' => 0,
                'cogs_total' => 0,
            ]);

            foreach ($allocations as $allocation) {
                $cogsAmount = round(((float) $allocation['purchase_price']) * $allocation['quantity'], 2);
                $itemCogs += $cogsAmount;

                SaleLotAllocation::query()->create([
                    'sale_item_id' => $saleItem->id,
                    'inventory_lot_id' => $allocation['lot_id'],
                    'quantity' => $allocation['quantity'],
                    'purchase_price' => $allocation['purchase_price'],
                    'cogs_amount' => $cogsAmount,
                ]);
            }

            $saleItem->update(['cogs_total' => round($itemCogs, 2)]);

            return $this->recalculateTotals($sale->fresh());
        });
    }

    public function updateItemQuantity(Sale $sale, SaleItem $item, int $quantity): Sale
    {
        $this->assertConfirmed($sale);
        $this->assertItemBelongsToSale($sale, $item);

        if ($quantity < 0) {
            throw new InvalidArgumentException('La cantidad no puede ser negativa.');
        }

        if ($quantity === 0) {
            return $this->removeItem($sale, $item);
        }

        return DB::transaction(function () use ($sale, $item, $quantity) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $item = SaleItem::query()->lockForUpdate()->with(['product', 'lotAllocations'])->findOrFail($item->id);
            $current = (int) $item->quantity;

            if ($quantity === $current) {
                return $sale->fresh(['customer', 'seller', 'shippingCarrier', 'items.product', 'items.lotAllocations.inventoryLot']);
            }

            if ($quantity > $current) {
                $delta = $quantity - $current;
                $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
                $allocations = $this->fifo->consume($product, $delta, [
                    'type' => 'sale',
                    'id' => $sale->id,
                    'occurred_at' => now(),
                    'notes' => 'Ajuste venta '.$sale->number.' (subir cantidad)',
                ]);

                $itemCogs = (float) $item->cogs_total;
                foreach ($allocations as $allocation) {
                    $cogsAmount = round(((float) $allocation['purchase_price']) * $allocation['quantity'], 2);
                    $itemCogs += $cogsAmount;

                    SaleLotAllocation::query()->create([
                        'sale_item_id' => $item->id,
                        'inventory_lot_id' => $allocation['lot_id'],
                        'quantity' => $allocation['quantity'],
                        'purchase_price' => $allocation['purchase_price'],
                        'cogs_amount' => $cogsAmount,
                    ]);
                }

                $item->update([
                    'quantity' => $quantity,
                    'cogs_total' => round($itemCogs, 2),
                ]);
            } else {
                $delta = $current - $quantity;
                $this->restoreQuantityFromItem($sale, $item, $delta);
                $item->refresh()->load('lotAllocations');
                $item->update([
                    'quantity' => $quantity,
                    'cogs_total' => round((float) $item->lotAllocations->sum('cogs_amount'), 2),
                ]);
            }

            return $this->recalculateTotals($sale->fresh());
        });
    }

    public function removeItem(Sale $sale, SaleItem $item): Sale
    {
        $this->assertConfirmed($sale);
        $this->assertItemBelongsToSale($sale, $item);

        return DB::transaction(function () use ($sale, $item) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $item = SaleItem::query()->lockForUpdate()->with('lotAllocations')->findOrFail($item->id);

            if ($sale->items()->count() <= 1) {
                throw new RuntimeException('La venta debe quedar con al menos un producto. Anúlala si quieres cancelarla.');
            }

            $restore = [];
            foreach ($item->lotAllocations as $allocation) {
                $restore[] = [
                    'lot_id' => $allocation->inventory_lot_id,
                    'quantity' => $allocation->quantity,
                ];
            }

            if ($restore !== []) {
                $this->fifo->restore($restore, [
                    'type' => 'sale',
                    'id' => $sale->id,
                    'occurred_at' => now(),
                    'notes' => 'Ajuste venta '.$sale->number.' (quitar ítem)',
                ]);
            }

            $item->lotAllocations()->delete();
            $item->delete();

            return $this->recalculateTotals($sale->fresh());
        });
    }

    /**
     * @param  array{shipping_amount?:float|int|string|null, notes?:?string}  $data
     */
    public function updateShipping(Sale $sale, array $data): Sale
    {
        $this->assertConfirmed($sale);

        return DB::transaction(function () use ($sale, $data) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if (array_key_exists('notes', $data)) {
                $sale->notes = $data['notes'];
                $sale->save();
            }

            return $this->recalculateTotals($sale, [
                'shipping_amount' => $data['shipping_amount'] ?? $sale->shipping_amount,
            ]);
        });
    }

    /**
     * @param  array{shipping_amount?:float|int|string|null}|null  $overrides
     */
    public function recalculateTotals(Sale $sale, ?array $overrides = null): Sale
    {
        return DB::transaction(function () use ($sale, $overrides) {
            $sale = Sale::query()
                ->lockForUpdate()
                ->with(['items.product', 'items.lotAllocations', 'shippingCarrier'])
                ->findOrFail($sale->id);

            if ($sale->items->isEmpty()) {
                throw new InvalidArgumentException('La venta debe tener al menos un producto.');
            }

            $vatRate = (float) $sale->vat_rate;
            $computed = [];
            $subtotal = 0.0;
            $subtotalWithVat = 0.0;

            foreach ($sale->items as $item) {
                $quantity = (int) $item->quantity;
                $unitPrice = (float) $item->unit_price_without_vat;
                $unitPriceWithVat = price_with_vat($unitPrice);
                $discountPercent = (float) $item->discount_percent;
                $discountAmount = (float) $item->discount_amount;

                $grossWithVat = round($quantity * $unitPriceWithVat, 2);
                $percentDiscountWithVat = round($grossWithVat * ($discountPercent / 100), 2);
                $lineDiscountWithVat = min($grossWithVat, round($percentDiscountWithVat + $discountAmount, 2));
                $netWithVat = round($grossWithVat - $lineDiscountWithVat, 2);
                $lineSubtotal = round($netWithVat / (1 + $vatRate), 2);

                $computed[$item->id] = [
                    'line_subtotal' => $lineSubtotal,
                    'line_net_with_vat' => $netWithVat,
                ];
                $subtotal += $lineSubtotal;
                $subtotalWithVat += $netWithVat;
            }

            $subtotal = round($subtotal, 2);
            $subtotalWithVat = round($subtotalWithVat, 2);
            $globalDiscountPercent = (float) $sale->discount_percent;
            $globalDiscountAmountWithVat = (float) $sale->discount_amount;
            $percentGlobalWithVat = round($subtotalWithVat * ($globalDiscountPercent / 100), 2);
            $discountTotalWithVat = min($subtotalWithVat, round($percentGlobalWithVat + $globalDiscountAmountWithVat, 2));
            $netWithVat = round($subtotalWithVat - $discountTotalWithVat, 2);
            $taxableBase = round($netWithVat / (1 + $vatRate), 2);
            $vatAmount = round($netWithVat - $taxableBase, 2);

            $hasShipping = (bool) $sale->has_shipping;
            $shippingAmount = 0.0;
            if ($hasShipping) {
                $shippingAmount = array_key_exists('shipping_amount', $overrides ?? [])
                    ? round((float) ($overrides['shipping_amount'] ?? 0), 2)
                    : round((float) $sale->shipping_amount, 2);

                if ($shippingAmount < 0) {
                    $shippingAmount = 0.0;
                }

                $hasFreeShippingProduct = $sale->items->contains(
                    fn (SaleItem $item) => (bool) $item->product?->free_shipping
                );

                if ($hasFreeShippingProduct) {
                    $shippingAmount = 0.0;
                }
            }

            $total = round($netWithVat + $shippingAmount, 2);

            $carrierShippingCost = 0.0;
            $carrierCommission = 0.0;
            if ($hasShipping && $sale->shipping_carrier_id) {
                $carrier = $sale->shippingCarrier
                    ?? ShippingCarrier::query()->find($sale->shipping_carrier_id);
                if ($carrier) {
                    $costs = $carrier->costsForTotal($total);
                    $carrierShippingCost = $costs['shipping_cost'];
                    $carrierCommission = $costs['commission_amount'];
                }
            }

            $discountRatio = $subtotal > 0 ? ($taxableBase / $subtotal) : 1;
            $saleCogs = 0.0;

            foreach ($sale->items as $item) {
                $lineSubtotal = $computed[$item->id]['line_subtotal'];
                $allocatedSubtotal = round($lineSubtotal * $discountRatio, 2);
                $lineVat = round($allocatedSubtotal * $vatRate, 2);
                $lineTotal = round($allocatedSubtotal + $lineVat, 2);
                $itemCogs = round((float) $item->lotAllocations->sum('cogs_amount'), 2);
                $saleCogs += $itemCogs;

                $item->update([
                    'line_subtotal' => $allocatedSubtotal,
                    'line_vat' => $lineVat,
                    'line_total' => $lineTotal,
                    'cogs_total' => $itemCogs,
                ]);
            }

            $saleCogs = round($saleCogs, 2);

            $sale->update([
                'subtotal_without_vat' => $subtotal,
                'taxable_base' => $taxableBase,
                'vat_amount' => $vatAmount,
                'shipping_amount' => $shippingAmount,
                'carrier_shipping_cost' => $carrierShippingCost,
                'carrier_commission_amount' => $carrierCommission,
                'total' => $total,
                'cogs_total' => $saleCogs,
                'gross_margin' => round($taxableBase - $saleCogs, 2),
            ]);

            return $sale->fresh([
                'customer',
                'seller',
                'shippingCarrier',
                'items.product',
                'items.lotAllocations.inventoryLot',
            ]);
        });
    }

    private function restoreQuantityFromItem(Sale $sale, SaleItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $remaining = $quantity;
        $restore = [];

        foreach ($item->lotAllocations->sortByDesc('id') as $allocation) {
            if ($remaining <= 0) {
                break;
            }

            $available = (int) $allocation->quantity;
            $take = min($available, $remaining);
            $restore[] = [
                'lot_id' => $allocation->inventory_lot_id,
                'quantity' => $take,
            ];

            if ($take === $available) {
                $allocation->delete();
            } else {
                $newQty = $available - $take;
                $allocation->update([
                    'quantity' => $newQty,
                    'cogs_amount' => round((float) $allocation->purchase_price * $newQty, 2),
                ]);
            }

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new RuntimeException('No se pudo restaurar el stock de la línea (asignaciones FIFO incompletas).');
        }

        if ($restore !== []) {
            $this->fifo->restore($restore, [
                'type' => 'sale',
                'id' => $sale->id,
                'occurred_at' => now(),
                'notes' => 'Ajuste venta '.$sale->number.' (bajar cantidad)',
            ]);
        }
    }

    private function assertConfirmed(Sale $sale): void
    {
        if (! $sale->isConfirmed()) {
            throw new RuntimeException('Solo se pueden editar ventas confirmadas.');
        }
    }

    private function assertItemBelongsToSale(Sale $sale, SaleItem $item): void
    {
        if ((int) $item->sale_id !== (int) $sale->id) {
            throw new InvalidArgumentException('El ítem no pertenece a esta venta.');
        }
    }

    public function nextNumber(?Seller $seller = null): string
    {
        $sellerPrefix = $seller?->saleNumberPrefix() ?? 'V-';
        $prefix = $sellerPrefix.now()->format('Ymd').'-';
        $last = Sale::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = 1;
        if ($last) {
            $sequence = ((int) substr($last, -4)) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
