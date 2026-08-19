<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductSupplier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class FifoInventoryService
{
    /**
     * Register a new stock receipt as a FIFO lot.
     * Covers open on-demand sale allocations first (real COGS), leftover stays in lot.
     */
    public function receiveStock(array $data): InventoryLot
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            $quantity = (int) $data['quantity'];
            $unitPrice = round((float) $data['purchase_price'], 2);

            $productSupplier = ProductSupplier::query()
                ->where('product_id', $product->id)
                ->where('supplier_id', $data['supplier_id'])
                ->firstOrFail();

            $lot = InventoryLot::query()->create([
                'lot_number' => $data['lot_number'] ?? $this->generateLotNumber($product),
                'product_id' => $product->id,
                'supplier_id' => $data['supplier_id'],
                'quantity_received' => $quantity,
                'quantity_remaining' => $quantity,
                'purchase_price' => $unitPrice,
                'amount_paid' => 0,
                'received_at' => $data['received_at'],
                'invoice_reference' => $data['invoice_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $productSupplier->update([
                'purchase_price' => $unitPrice,
            ]);

            $covered = $this->coverOnDemandAllocations($lot, $quantity, $unitPrice);
            $remaining = $quantity - $covered;
            $lot->update(['quantity_remaining' => $remaining]);

            $note = $data['notes'] ?? ('Entrada lote '.$lot->lot_number);
            if ($covered > 0) {
                $note = trim($note.' · cubre '.$covered.' on-demand');
            }

            $this->logMovement([
                'product_id' => $product->id,
                'inventory_lot_id' => $lot->id,
                'type' => InventoryMovement::TYPE_ENTRADA,
                'quantity' => $quantity,
                'unit_cost' => $unitPrice,
                'reference_type' => 'inventory_lot',
                'reference_id' => $lot->id,
                'occurred_at' => $data['received_at'],
                'notes' => $note,
            ]);

            $fresh = $lot->fresh();
            $fresh->setAttribute('on_demand_covered', $covered);

            return $fresh;
        });
    }

    /**
     * Units sold on-demand still waiting for a physical lot (for UI hints).
     */
    public function pendingOnDemandQuantity(int $productId): int
    {
        return (int) \App\Models\SaleLotAllocation::query()
            ->whereNull('inventory_lot_id')
            ->whereHas('saleItem', fn ($q) => $q->where('product_id', $productId))
            ->whereHas('saleItem.sale', fn ($q) => $q->onDemandVisible())
            ->sum('quantity');
    }

    /**
     * Use leftover quantity_remaining on a lot to cover open on-demand sales
     * (for lots received before cover existed, or manual repair).
     *
     * @return int units covered
     */
    public function applyLotRemainingToOnDemand(InventoryLot $lot): int
    {
        return DB::transaction(function () use ($lot) {
            $lot = InventoryLot::query()->lockForUpdate()->findOrFail($lot->id);
            $available = (int) $lot->quantity_remaining;
            if ($available <= 0) {
                return 0;
            }

            $covered = $this->coverOnDemandAllocations(
                $lot,
                $available,
                (float) $lot->purchase_price
            );

            if ($covered > 0) {
                $lot->update([
                    'quantity_remaining' => $available - $covered,
                ]);
            }

            return $covered;
        });
    }

    /**
     * Assign incoming stock to oldest open on-demand allocations.
     *
     * @return int units covered
     */
    private function coverOnDemandAllocations(InventoryLot $lot, int $available, float $unitPrice): int
    {
        if ($available <= 0) {
            return 0;
        }

        $allocations = \App\Models\SaleLotAllocation::query()
            ->select('sale_lot_allocations.*')
            ->join('sale_items', 'sale_items.id', '=', 'sale_lot_allocations.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sale_lot_allocations.inventory_lot_id')
            ->where('sale_items.product_id', $lot->product_id)
            ->whereIn('sales.status', \App\Models\Sale::ON_DEMAND_STATUSES)
            ->orderBy('sales.sold_at')
            ->orderBy('sale_lot_allocations.id')
            ->lockForUpdate()
            ->with(['saleItem.sale'])
            ->get();

        $toCover = $available;
        $covered = 0;
        $affectedSaleIds = [];

        foreach ($allocations as $allocation) {
            if ($toCover <= 0) {
                break;
            }

            $need = (int) $allocation->quantity;
            if ($need <= 0) {
                continue;
            }

            $take = min($need, $toCover);
            $estimatedPrice = (float) $allocation->purchase_price;

            if ($take === $need) {
                $allocation->update([
                    'inventory_lot_id' => $lot->id,
                    'purchase_price' => $unitPrice,
                    'cogs_amount' => round($take * $unitPrice, 2),
                ]);
            } else {
                $remainder = $need - $take;
                $allocation->update([
                    'quantity' => $take,
                    'inventory_lot_id' => $lot->id,
                    'purchase_price' => $unitPrice,
                    'cogs_amount' => round($take * $unitPrice, 2),
                ]);

                \App\Models\SaleLotAllocation::query()->create([
                    'sale_item_id' => $allocation->sale_item_id,
                    'inventory_lot_id' => null,
                    'quantity' => $remainder,
                    'purchase_price' => $estimatedPrice,
                    'cogs_amount' => round($remainder * $estimatedPrice, 2),
                ]);
            }

            $toCover -= $take;
            $covered += $take;
            if ($allocation->saleItem?->sale_id) {
                $affectedSaleIds[] = (int) $allocation->saleItem->sale_id;
            }
        }

        foreach (array_unique($affectedSaleIds) as $saleId) {
            $this->recalculateSaleCogs($saleId);
        }

        return $covered;
    }

    private function recalculateSaleCogs(int $saleId): void
    {
        $sale = \App\Models\Sale::query()
            ->with('items.lotAllocations')
            ->lockForUpdate()
            ->find($saleId);

        if (! $sale || $sale->isVoided()) {
            return;
        }

        $saleCogs = 0.0;
        foreach ($sale->items as $item) {
            $itemCogs = round((float) $item->lotAllocations->sum('cogs_amount'), 2);
            $item->update(['cogs_total' => $itemCogs]);
            $saleCogs += $itemCogs;
        }

        $saleCogs = round($saleCogs, 2);
        $sale->update([
            'cogs_total' => $saleCogs,
            'gross_margin' => round((float) $sale->taxable_base - $saleCogs, 2),
        ]);
    }

    /**
     * Correct a stock receipt lot (rectify mistaken entry).
     */
    public function correctLot(InventoryLot $lot, array $data): InventoryLot
    {
        return DB::transaction(function () use ($lot, $data) {
            $lot = InventoryLot::query()->lockForUpdate()->findOrFail($lot->id);
            $soldQty = (int) $lot->quantity_received - (int) $lot->quantity_remaining;
            $unused = $soldQty === 0;

            $oldRemaining = (int) $lot->quantity_remaining;
            $notes = trim((string) ($data['notes'] ?? ''));

            if ($unused) {
                $newQty = (int) $data['quantity'];
                if ($newQty < 1) {
                    throw new InvalidArgumentException('La cantidad corregida debe ser al menos 1. Para anular usa anular entrada.');
                }

                $lot->update([
                    'quantity_received' => $newQty,
                    'quantity_remaining' => $newQty,
                    'purchase_price' => $data['purchase_price'],
                    'received_at' => $data['received_at'],
                    'invoice_reference' => $data['invoice_reference'] ?? $lot->invoice_reference,
                    'notes' => $notes !== '' ? $notes : $lot->notes,
                    'supplier_id' => $data['supplier_id'] ?? $lot->supplier_id,
                ]);

                $delta = $newQty - $oldRemaining;
            } else {
                $newRemaining = (int) $data['quantity_remaining'];
                if ($newRemaining < 0) {
                    throw new InvalidArgumentException('La cantidad restante no puede ser negativa.');
                }

                // Cannot go below what was already sold conceptually: remaining can only change;
                // received stays, unless we increase remaining above original received.
                $delta = $newRemaining - $oldRemaining;
                $lot->quantity_remaining = $newRemaining;
                if ($delta > 0) {
                    $lot->quantity_received += $delta;
                }
                if ($notes !== '') {
                    $lot->notes = $notes;
                }
                $lot->save();
            }

            if ($delta !== 0) {
                $this->logMovement([
                    'product_id' => $lot->product_id,
                    'inventory_lot_id' => $lot->id,
                    'type' => InventoryMovement::TYPE_AJUSTE,
                    'quantity' => $delta,
                    'unit_cost' => $lot->purchase_price,
                    'reference_type' => 'inventory_lot',
                    'reference_id' => $lot->id,
                    'occurred_at' => now(),
                    'notes' => $notes !== '' ? $notes : 'Rectificación de entrada '.$lot->lot_number,
                ]);
            }

            return $lot->fresh();
        });
    }

    /**
     * Void an unused stock receipt lot.
     */
    public function voidLot(InventoryLot $lot, ?string $reason = null): InventoryLot
    {
        return DB::transaction(function () use ($lot, $reason) {
            $lot = InventoryLot::query()->lockForUpdate()->findOrFail($lot->id);

            if ((int) $lot->quantity_remaining !== (int) $lot->quantity_received) {
                throw new RuntimeException(
                    'No se puede anular: el lote ya tiene salidas por venta. Rectifica solo la cantidad restante.'
                );
            }

            $qty = (int) $lot->quantity_remaining;
            if ($qty <= 0) {
                throw new RuntimeException('Este lote ya está anulado o vacío.');
            }

            $lot->update([
                'quantity_remaining' => 0,
                'notes' => trim(($lot->notes ? $lot->notes."\n" : '').'ANULADO: '.($reason ?: 'Entrada anulada')),
            ]);

            $this->logMovement([
                'product_id' => $lot->product_id,
                'inventory_lot_id' => $lot->id,
                'type' => InventoryMovement::TYPE_ANULACION_ENTRADA,
                'quantity' => -1 * $qty,
                'unit_cost' => $lot->purchase_price,
                'reference_type' => 'inventory_lot',
                'reference_id' => $lot->id,
                'occurred_at' => now(),
                'notes' => $reason ?: 'Anulación de entrada '.$lot->lot_number,
            ]);

            return $lot->fresh();
        });
    }

    /**
     * Consume stock using FIFO (oldest lots first).
     *
     * @return list<array{lot_id:int, quantity:int, purchase_price:string}>
     */
    public function consume(Product $product, int $quantity, ?array $reference = null): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad a consumir debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($product, $quantity, $reference) {
            $lots = InventoryLot::query()
                ->where('product_id', $product->id)
                ->where('quantity_remaining', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = (int) $lots->sum('quantity_remaining');
            $allowOnDemandShortfall = (bool) ($product->on_demand && ($reference['allow_on_demand'] ?? false));

            if ($available < $quantity && ! $allowOnDemandShortfall) {
                throw new RuntimeException(
                    "Stock insuficiente para {$product->code}. Disponible: {$available}, solicitado: {$quantity}."
                );
            }

            $remaining = $quantity;
            $allocations = [];

            foreach ($lots as $lot) {
                if ($remaining === 0) {
                    break;
                }

                $take = min($lot->quantity_remaining, $remaining);
                $lot->quantity_remaining -= $take;
                $lot->save();

                $allocations[] = [
                    'lot_id' => $lot->id,
                    'quantity' => $take,
                    'purchase_price' => (string) $lot->purchase_price,
                ];

                $this->logMovement([
                    'product_id' => $product->id,
                    'inventory_lot_id' => $lot->id,
                    'type' => $reference['movement_type'] ?? InventoryMovement::TYPE_VENTA,
                    'quantity' => -1 * $take,
                    'unit_cost' => $lot->purchase_price,
                    'reference_type' => $reference['type'] ?? null,
                    'reference_id' => $reference['id'] ?? null,
                    'occurred_at' => $reference['occurred_at'] ?? now(),
                    'notes' => $reference['notes'] ?? 'Salida por venta',
                ]);

                $remaining -= $take;
            }

            if ($remaining > 0 && $allowOnDemandShortfall) {
                $unitCost = $product->estimatedUnitCost();
                $allocations[] = [
                    'lot_id' => null,
                    'quantity' => $remaining,
                    'purchase_price' => number_format($unitCost, 2, '.', ''),
                ];

                $this->logMovement([
                    'product_id' => $product->id,
                    'inventory_lot_id' => null,
                    'type' => $reference['movement_type'] ?? InventoryMovement::TYPE_VENTA,
                    'quantity' => -1 * $remaining,
                    'unit_cost' => $unitCost,
                    'reference_type' => $reference['type'] ?? null,
                    'reference_id' => $reference['id'] ?? null,
                    'occurred_at' => $reference['occurred_at'] ?? now(),
                    'notes' => trim(($reference['notes'] ?? 'Salida por venta').' · on-demand sin stock'),
                ]);
            }

            return $allocations;
        });
    }

    /**
     * Restore previously consumed FIFO allocations (e.g. voided sale).
     *
     * @param  list<array{lot_id:int, quantity:int}>|iterable<int, array{lot_id:int, quantity:int}>  $allocations
     */
    public function restore(iterable $allocations, ?array $reference = null): void
    {
        DB::transaction(function () use ($allocations, $reference) {
            foreach ($allocations as $allocation) {
                $lotId = $allocation['lot_id'] ?? null;
                if ($lotId === null) {
                    // On-demand shortfall had no physical lot to restore.
                    continue;
                }

                $lot = InventoryLot::query()
                    ->lockForUpdate()
                    ->findOrFail($lotId);

                $qty = (int) $allocation['quantity'];
                $lot->quantity_remaining += $qty;
                $lot->save();

                $this->logMovement([
                    'product_id' => $lot->product_id,
                    'inventory_lot_id' => $lot->id,
                    'type' => $reference['movement_type'] ?? InventoryMovement::TYPE_ANULACION_VENTA,
                    'quantity' => $qty,
                    'unit_cost' => $lot->purchase_price,
                    'reference_type' => $reference['type'] ?? null,
                    'reference_id' => $reference['id'] ?? null,
                    'occurred_at' => $reference['occurred_at'] ?? now(),
                    'notes' => $reference['notes'] ?? 'Restauración por anulación de venta',
                ]);
            }
        });
    }

    private function logMovement(array $data): InventoryMovement
    {
        return InventoryMovement::query()->create([
            'product_id' => $data['product_id'],
            'inventory_lot_id' => $data['inventory_lot_id'] ?? null,
            'type' => $data['type'],
            'quantity' => (int) $data['quantity'],
            'unit_cost' => $data['unit_cost'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    private function generateLotNumber(Product $product): string
    {
        return sprintf(
            'LOT-%s-%s-%s',
            strtoupper($product->code),
            now()->format('YmdHis'),
            strtoupper(substr(uniqid(), -4))
        );
    }
}
