<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SupplierPaymentService
{
    /**
     * Paga todo el saldo pendiente del proveedor (FIFO en lotes).
     */
    public function payFull(
        int $supplierId,
        ?string $paymentMethod = null,
        ?string $reference = null,
        ?string $notes = null,
        mixed $paidAt = null,
    ): SupplierPayment {
        $lots = $this->payableLotsForSupplier($supplierId);
        $balance = round((float) $lots->sum(fn (InventoryLot $lot) => $lot->balanceDue()), 2);

        if ($balance <= 0.009) {
            throw new RuntimeException('Este proveedor no tiene saldo pendiente.');
        }

        return $this->create([
            'supplier_id' => $supplierId,
            'paid_at' => $paidAt ?? now(),
            'amount' => $balance,
            'payment_method' => $paymentMethod ?: 'transfer',
            'reference' => $reference,
            'notes' => $notes ?: 'Pago completo del saldo',
            'lot_ids' => null,
        ]);
    }

    /**
     * @param  array{
     *   supplier_id:int,
     *   paid_at:mixed,
     *   amount:float|int|string,
     *   payment_method?:string,
     *   reference?:?string,
     *   notes?:?string,
     *   lot_ids?:list<int>|null
     * }  $data
     */
    public function create(array $data): SupplierPayment
    {
        return DB::transaction(function () use ($data) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id']);
            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw new InvalidArgumentException('El monto del pago debe ser mayor a cero.');
            }

            $lots = $this->payableLotsForSupplier(
                (int) $supplier->id,
                isset($data['lot_ids']) ? array_map('intval', (array) $data['lot_ids']) : null
            );

            if ($lots->isEmpty()) {
                throw new RuntimeException('Este proveedor no tiene lotes con saldo pendiente.');
            }

            $totalDue = round((float) $lots->sum(fn (InventoryLot $lot) => $lot->balanceDue()), 2);
            if ($amount - $totalDue > 0.009) {
                throw new InvalidArgumentException(
                    'El pago ('.number_format($amount, 2).') supera el saldo adeudado ('.number_format($totalDue, 2).').'
                );
            }

            $payment = SupplierPayment::query()->create([
                'supplier_id' => $supplier->id,
                'paid_at' => $data['paid_at'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'transfer',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $remaining = $amount;
            foreach ($lots as $lot) {
                if ($remaining <= 0.009) {
                    break;
                }

                $lot = InventoryLot::query()->lockForUpdate()->findOrFail($lot->id);
                $due = $lot->balanceDue();
                if ($due <= 0.009) {
                    continue;
                }

                $apply = round(min($due, $remaining), 2);
                if ($apply <= 0) {
                    continue;
                }

                SupplierPaymentAllocation::query()->create([
                    'supplier_payment_id' => $payment->id,
                    'inventory_lot_id' => $lot->id,
                    'amount' => $apply,
                ]);

                $lot->update([
                    'amount_paid' => round((float) $lot->amount_paid + $apply, 2),
                ]);

                $remaining = round($remaining - $apply, 2);
            }

            if ($remaining > 0.009) {
                throw new RuntimeException('No se pudo asignar el pago completo a lotes pendientes.');
            }

            return $payment->fresh(['allocations.inventoryLot', 'supplier']);
        });
    }

    /**
     * @param  list<int>|null  $lotIds
     * @return Collection<int, InventoryLot>
     */
    public function payableLotsForSupplier(int $supplierId, ?array $lotIds = null): Collection
    {
        return InventoryLot::query()
            ->with('product')
            ->where('supplier_id', $supplierId)
            ->where('quantity_received', '>', 0)
            ->when($lotIds !== null && $lotIds !== [], fn ($q) => $q->whereIn('id', $lotIds))
            ->orderBy('received_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (InventoryLot $lot) => $lot->balanceDue() > 0.009)
            ->values();
    }

    /**
     * @return Collection<int, object{supplier: Supplier, purchased: float, paid: float, balance: float}>
     */
    public function balancesBySupplier(): Collection
    {
        $lots = InventoryLot::query()
            ->with('supplier')
            ->where('quantity_received', '>', 0)
            ->get();

        return $lots
            ->groupBy('supplier_id')
            ->map(function (Collection $group) {
                /** @var Supplier|null $supplier */
                $supplier = $group->first()?->supplier;
                $purchased = round((float) $group->sum(fn (InventoryLot $lot) => $lot->purchaseCost()), 2);
                $paid = round((float) $group->sum(fn (InventoryLot $lot) => (float) $lot->amount_paid), 2);

                return (object) [
                    'supplier' => $supplier,
                    'purchased' => $purchased,
                    'paid' => $paid,
                    'balance' => round($purchased - $paid, 2),
                ];
            })
            ->filter(fn ($row) => $row->supplier !== null)
            ->sortBy(fn ($row) => $row->supplier->name)
            ->values();
    }
}
