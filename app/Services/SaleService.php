<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleLotAllocation;
use App\Models\Seller;
use App\Models\ShippingCarrier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SaleService
{
    public function __construct(private FifoInventoryService $fifo) {}

    public function create(array $data): Sale
    {
        $items = $this->normalizeSaleItems($data);

        if ($items->isEmpty()) {
            throw new InvalidArgumentException('La venta debe tener al menos un producto o combo.');
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
                    'combo_id' => $item['combo_id'] ?? null,
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

                $comboIds = collect($computedItems)
                    ->pluck('combo_id')
                    ->filter()
                    ->unique()
                    ->values();
                $freeShippingComboIds = $comboIds->isEmpty()
                    ? collect()
                    : Combo::query()
                        ->whereIn('id', $comboIds)
                        ->where('free_shipping', true)
                        ->pluck('id');

                $hasFreeShippingProduct = collect($computedItems)->contains(
                    fn (array $computed) => (bool) $computed['product']->free_shipping
                        || ($computed['combo_id'] && $freeShippingComboIds->contains($computed['combo_id']))
                );

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
                'status_changed_at' => now(),
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
                'channel' => $data['channel'] ?? Sale::CHANNEL_CRM,
                'created_by_user_id' => auth()->id(),
                'external_order_id' => $data['external_order_id'] ?? null,
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
                    'allow_on_demand' => true,
                ]);
                $itemCogs = 0.0;

                $saleItem = SaleItem::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $computed['product']->id,
                    'combo_id' => $computed['combo_id'] ?? null,
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

            return $sale->fresh(['customer', 'seller', 'items.product', 'items.combo', 'items.lotAllocations.inventoryLot']);
        });
    }

    public function void(Sale $sale): Sale
    {
        if ($sale->isVoided()) {
            throw new RuntimeException('La venta ya está anulada.');
        }

        return DB::transaction(function () use ($sale) {
            $sale->load('items.lotAllocations');

            // Devolución ya reingresó stock; anular después no debe duplicarlo.
            if (! $sale->isReturned()) {
                $this->restoreSaleStock($sale, 'Restauración por anulación '.$sale->number);
            }

            $sale->update([
                'status' => Sale::STATUS_VOIDED,
                'voided_at' => now(),
                'voided_by_user_id' => auth()->id(),
                'status_changed_at' => now(),
            ]);

            return $sale->fresh(['customer', 'seller', 'items.product', 'items.lotAllocations.inventoryLot']);
        });
    }

    public function markDelivered(Sale $sale): Sale
    {
        if ($sale->isVoided()) {
            throw new RuntimeException('No se puede marcar entregada una venta anulada.');
        }

        if ($sale->isDelivered()) {
            return $sale;
        }

        $sale->forceFill([
            'status' => Sale::STATUS_DELIVERED,
            'status_changed_at' => now(),
        ])->save();

        return $sale->fresh();
    }

    /**
     * Devolución: el producto vuelve → reingresa stock FIFO (ciclo de envío cumplido).
     * Distinto de anular: ahí el pedido no cerró el ciclo (error / cancelación previa).
     */
    public function markReturned(Sale $sale): Sale
    {
        if ($sale->isVoided()) {
            throw new RuntimeException('No se puede marcar devolución en una venta anulada.');
        }

        if ($sale->isReturned()) {
            return $sale;
        }

        if (! $sale->canProgressToStatus(Sale::STATUS_RETURNED)) {
            throw new RuntimeException('Esta venta no se puede marcar como devolución desde su estado actual.');
        }

        return DB::transaction(function () use ($sale) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $sale->load('items.lotAllocations');

            $this->restoreSaleStock($sale, 'Restauración por devolución '.$sale->number);

            $sale->update([
                'status' => Sale::STATUS_RETURNED,
                'status_changed_at' => now(),
            ]);

            return $sale->fresh(['customer', 'seller', 'items.product', 'items.lotAllocations.inventoryLot']);
        });
    }

    /**
     * @return list<array{lot_id: int, quantity: int}>
     */
    private function restoreSaleStock(Sale $sale, string $notes): array
    {
        $restore = [];
        foreach ($sale->items as $item) {
            foreach ($item->lotAllocations as $allocation) {
                if ($allocation->inventory_lot_id === null) {
                    continue;
                }
                $restore[] = [
                    'lot_id' => (int) $allocation->inventory_lot_id,
                    'quantity' => (int) $allocation->quantity,
                ];
            }
        }

        if ($restore !== []) {
            $this->fifo->restore($restore, [
                'type' => 'sale',
                'id' => $sale->id,
                'occurred_at' => now(),
                'notes' => $notes,
            ]);
        }

        return $restore;
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
                'allow_on_demand' => true,
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
        return $this->updateItem($sale, $item, ['quantity' => $quantity]);
    }

    /**
     * @param  array{quantity:int, unit_price_with_vat?:float|int|string|null, discount_percent?:float|int|string|null, discount_amount?:float|int|string|null}  $data
     */
    public function updateItem(Sale $sale, SaleItem $item, array $data): Sale
    {
        $this->assertConfirmed($sale);
        $this->assertItemBelongsToSale($sale, $item);

        $quantity = (int) ($data['quantity'] ?? $item->quantity);
        if ($quantity < 0) {
            throw new InvalidArgumentException('La cantidad no puede ser negativa.');
        }

        if ($quantity === 0) {
            return $this->removeItem($sale, $item);
        }

        return DB::transaction(function () use ($sale, $item, $quantity, $data) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            $item = SaleItem::query()->lockForUpdate()->with(['product', 'lotAllocations'])->findOrFail($item->id);
            $current = (int) $item->quantity;
            $vatRate = (float) $sale->vat_rate;

            $priceTouched = array_key_exists('unit_price_with_vat', $data)
                || array_key_exists('discount_percent', $data)
                || array_key_exists('discount_amount', $data);

            $unitPriceWithVat = array_key_exists('unit_price_with_vat', $data)
                ? round((float) $data['unit_price_with_vat'], 2)
                : round(price_with_vat((float) $item->unit_price_without_vat), 2);
            $unitPrice = round($unitPriceWithVat / (1 + $vatRate), 4);
            $discountPercent = array_key_exists('discount_percent', $data)
                ? (float) $data['discount_percent']
                : (float) $item->discount_percent;
            $discountAmount = array_key_exists('discount_amount', $data)
                ? (float) $data['discount_amount']
                : (float) $item->discount_amount;

            if ($quantity > $current) {
                $delta = $quantity - $current;
                $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
                $allocations = $this->fifo->consume($product, $delta, [
                    'type' => 'sale',
                    'id' => $sale->id,
                    'occurred_at' => now(),
                    'notes' => 'Ajuste venta '.$sale->number.' (subir cantidad)',
                    'allow_on_demand' => true,
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
                    'unit_price_without_vat' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                ]);
            } elseif ($quantity < $current) {
                $delta = $current - $quantity;
                $this->restoreQuantityFromItem($sale, $item, $delta);
                $item->refresh()->load('lotAllocations');
                $item->update([
                    'quantity' => $quantity,
                    'cogs_total' => round((float) $item->lotAllocations->sum('cogs_amount'), 2),
                    'unit_price_without_vat' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                ]);
            } elseif ($priceTouched) {
                $item->update([
                    'unit_price_without_vat' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                ]);
            } else {
                return $sale->fresh(['customer', 'seller', 'shippingCarrier', 'items.product', 'items.lotAllocations.inventoryLot']);
            }

            return $this->recalculateTotals($sale->fresh());
        });
    }

    /**
     * @param  array{discount_percent?:float|int|string|null, discount_amount?:float|int|string|null, target_products_total_with_vat?:float|int|string|null}  $data
     */
    public function updateDiscounts(Sale $sale, array $data): Sale
    {
        $this->assertConfirmed($sale);

        return DB::transaction(function () use ($sale, $data) {
            $sale = Sale::query()->lockForUpdate()->with('items')->findOrFail($sale->id);
            $vatRate = (float) $sale->vat_rate;

            $discountPercent = round((float) ($data['discount_percent'] ?? $sale->discount_percent), 2);
            $discountAmount = round((float) ($data['discount_amount'] ?? $sale->discount_amount), 2);

            if (array_key_exists('target_products_total_with_vat', $data)
                && $data['target_products_total_with_vat'] !== null
                && $data['target_products_total_with_vat'] !== ''
            ) {
                $target = round((float) $data['target_products_total_with_vat'], 2);
                $grossWithVat = 0.0;

                foreach ($sale->items as $item) {
                    $unitWithVat = price_with_vat((float) $item->unit_price_without_vat);
                    $lineGross = round((int) $item->quantity * $unitWithVat, 2);
                    $linePercent = round($lineGross * (((float) $item->discount_percent) / 100), 2);
                    $lineDisc = min($lineGross, round($linePercent + (float) $item->discount_amount, 2));
                    $grossWithVat += round($lineGross - $lineDisc, 2);
                }

                $grossWithVat = round($grossWithVat, 2);
                if ($target > $grossWithVat) {
                    throw new InvalidArgumentException(
                        'El total de productos deseado ('.number_format($target, 2).') no puede ser mayor al bruto c/IVA ('.number_format($grossWithVat, 2).'). Sube el precio unitario o baja descuentos de línea.'
                    );
                }

                // Prefer amount discount so the target matches exactly after % = 0.
                $discountPercent = 0.0;
                $discountAmount = round(max(0, $grossWithVat - $target), 2);
            }

            $sale->update([
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
            ]);

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
                ->with(['items.product', 'items.combo', 'items.lotAllocations', 'shippingCarrier'])
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
                        || (bool) $item->combo?->free_shipping
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

            if ($allocation->inventory_lot_id !== null) {
                $restore[] = [
                    'lot_id' => $allocation->inventory_lot_id,
                    'quantity' => $take,
                ];
            }

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

    /**
     * Merge regular product lines with expanded combo lines.
     *
     * @return Collection<int, array{product_id: int, quantity: int, unit_price_with_vat: float, unit_price_without_vat: float, discount_percent: float, discount_amount: float, combo_id: ?int}>
     */
    private function normalizeSaleItems(array $data): Collection
    {
        $lines = collect($data['items'] ?? [])
            ->filter(fn (array $item) => ! empty($item['product_id']) && (int) ($item['quantity'] ?? 0) > 0)
            ->map(function (array $item) {
                $unitWithVat = isset($item['unit_price_with_vat'])
                    ? round((float) $item['unit_price_with_vat'], 2)
                    : null;

                return [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price_with_vat' => $unitWithVat,
                    'unit_price_without_vat' => isset($item['unit_price_without_vat'])
                        ? (float) $item['unit_price_without_vat']
                        : ($unitWithVat !== null ? price_without_vat($unitWithVat) : null),
                    'discount_percent' => (float) ($item['discount_percent'] ?? 0),
                    'discount_amount' => (float) ($item['discount_amount'] ?? 0),
                    'combo_id' => ! empty($item['combo_id']) ? (int) $item['combo_id'] : null,
                ];
            })
            ->values();

        $comboRows = collect($data['combos'] ?? [])
            ->filter(fn (array $row) => ! empty($row['combo_id']) && (int) ($row['quantity'] ?? 0) > 0)
            ->values();

        foreach ($comboRows as $row) {
            $combo = Combo::query()
                ->where('is_active', true)
                ->with('items.product')
                ->findOrFail((int) $row['combo_id']);

            foreach ($combo->expandToSaleLines((int) $row['quantity']) as $expanded) {
                $lines->push([
                    'product_id' => $expanded['product']->id,
                    'quantity' => $expanded['quantity'],
                    'unit_price_with_vat' => $expanded['unit_price_with_vat'],
                    'unit_price_without_vat' => $expanded['unit_price_without_vat'],
                    'discount_percent' => 0.0,
                    'discount_amount' => 0.0,
                    'combo_id' => $expanded['combo_id'],
                ]);
            }
        }

        return $lines->values();
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
