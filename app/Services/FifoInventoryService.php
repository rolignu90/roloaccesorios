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
     */
    public function receiveStock(array $data): InventoryLot
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);

            $productSupplier = ProductSupplier::query()
                ->where('product_id', $product->id)
                ->where('supplier_id', $data['supplier_id'])
                ->firstOrFail();

            $lot = InventoryLot::query()->create([
                'lot_number' => $data['lot_number'] ?? $this->generateLotNumber($product),
                'product_id' => $product->id,
                'supplier_id' => $data['supplier_id'],
                'quantity_received' => $data['quantity'],
                'quantity_remaining' => $data['quantity'],
                'purchase_price' => $data['purchase_price'],
                'received_at' => $data['received_at'],
                'invoice_reference' => $data['invoice_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $productSupplier->update([
                'purchase_price' => $data['purchase_price'],
            ]);

            $this->logMovement([
                'product_id' => $product->id,
                'inventory_lot_id' => $lot->id,
                'type' => InventoryMovement::TYPE_ENTRADA,
                'quantity' => (int) $data['quantity'],
                'unit_cost' => $data['purchase_price'],
                'reference_type' => 'inventory_lot',
                'reference_id' => $lot->id,
                'occurred_at' => $data['received_at'],
                'notes' => $data['notes'] ?? ('Entrada lote '.$lot->lot_number),
            ]);

            return $lot;
        });
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

            if ($available < $quantity) {
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
                $lot = InventoryLot::query()
                    ->lockForUpdate()
                    ->findOrFail($allocation['lot_id']);

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
