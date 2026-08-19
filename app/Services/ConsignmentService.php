<?php

namespace App\Services;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\ConsignmentLotAllocation;
use App\Models\ConsignmentPayment;
use App\Models\ConsignmentReturn;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ConsignmentService
{
    public function __construct(
        private FifoInventoryService $fifo,
        private CustomerPricingService $customerPricing,
    ) {
    }

    public function create(array $data): Consignment
    {
        $items = collect($data['items'] ?? [])
            ->filter(fn (array $item) => ! empty($item['product_id']) && (int) ($item['quantity'] ?? 0) > 0)
            ->values();

        if ($items->isEmpty()) {
            throw new InvalidArgumentException('La consignación debe tener al menos un producto.');
        }

        return DB::transaction(function () use ($data, $items) {
            $partyType = $data['party_type'];
            $sellerId = $partyType === Consignment::PARTY_SELLER ? ($data['seller_id'] ?? null) : null;
            $customerId = $partyType === Consignment::PARTY_CUSTOMER ? ($data['customer_id'] ?? null) : null;

            if ($partyType === Consignment::PARTY_SELLER && ! $sellerId) {
                throw new InvalidArgumentException('Selecciona el vendedor consignatario.');
            }
            if ($partyType === Consignment::PARTY_CUSTOMER && ! $customerId) {
                throw new InvalidArgumentException('Selecciona el cliente consignatario.');
            }

            $consignment = Consignment::query()->create([
                'number' => $data['number'] ?? $this->nextNumber(),
                'party_type' => $partyType,
                'seller_id' => $sellerId,
                'customer_id' => $customerId,
                'delivered_at' => $data['delivered_at'] ?? now(),
                'status' => Consignment::STATUS_OPEN,
                'total_with_vat' => 0,
                'returned_with_vat' => 0,
                'paid_with_vat' => 0,
                'balance_with_vat' => 0,
                'cogs_total' => 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $totalWithVat = 0.0;
            $cogsTotal = 0.0;

            foreach ($items as $row) {
                $product = Product::query()->lockForUpdate()->findOrFail($row['product_id']);
                $quantity = (int) $row['quantity'];
                if (array_key_exists('unit_price_with_vat', $row) && $row['unit_price_with_vat'] !== null && $row['unit_price_with_vat'] !== '') {
                    $unitWithVat = round((float) $row['unit_price_with_vat'], 2);
                } else {
                    $unitWithVat = round(
                        $this->customerPricing->resolveUnitPriceWithVat(
                            $customerId ? (int) $customerId : null,
                            $product,
                            $quantity
                        ),
                        2
                    );
                }

                if ($unitWithVat <= 0) {
                    throw new InvalidArgumentException(
                        "El producto {$product->code} no tiene precio de reventa; indícalo en la línea."
                    );
                }

                $unitWithoutVat = price_without_vat($unitWithVat);
                $lineTotal = round($quantity * $unitWithVat, 2);

                $item = ConsignmentItem::query()->create([
                    'consignment_id' => $consignment->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'quantity_returned' => 0,
                    'unit_price_with_vat' => $unitWithVat,
                    'unit_price_without_vat' => $unitWithoutVat,
                    'line_total_with_vat' => $lineTotal,
                    'cogs_total' => 0,
                ]);

                $allocations = $this->fifo->consume($product, $quantity, [
                    'type' => 'consignment',
                    'id' => $consignment->id,
                    'occurred_at' => $consignment->delivered_at,
                    'movement_type' => InventoryMovement::TYPE_CONSIGNACION,
                    'notes' => 'Salida por consignación '.$consignment->number,
                    'allow_on_demand' => true,
                ]);

                $itemCogs = 0.0;
                foreach ($allocations as $allocation) {
                    $cogs = round((int) $allocation['quantity'] * (float) $allocation['purchase_price'], 2);
                    ConsignmentLotAllocation::query()->create([
                        'consignment_item_id' => $item->id,
                        'inventory_lot_id' => $allocation['lot_id'],
                        'quantity' => $allocation['quantity'],
                        'quantity_returned' => 0,
                        'purchase_price' => $allocation['purchase_price'],
                        'cogs_amount' => $cogs,
                    ]);
                    $itemCogs += $cogs;
                }

                $item->update(['cogs_total' => round($itemCogs, 2)]);
                $totalWithVat += $lineTotal;
                $cogsTotal += $itemCogs;
            }

            $consignment->update([
                'total_with_vat' => round($totalWithVat, 2),
                'cogs_total' => round($cogsTotal, 2),
            ]);

            return $this->recalcTotals($consignment->fresh());
        });
    }

    public function addPayment(Consignment $consignment, array $data): ConsignmentPayment
    {
        return DB::transaction(function () use ($consignment, $data) {
            $consignment = Consignment::query()->lockForUpdate()->findOrFail($consignment->id);

            if (! $consignment->canReceivePayment()) {
                throw new RuntimeException('Esta consignación no acepta más pagos.');
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw new InvalidArgumentException('El monto del pago debe ser mayor a 0.');
            }

            $balance = (float) $consignment->balance_with_vat;
            if ($amount > $balance + 0.009) {
                throw new InvalidArgumentException('El pago no puede superar el saldo adeudado ('.money($balance).').');
            }

            $payment = ConsignmentPayment::query()->create([
                'consignment_id' => $consignment->id,
                'paid_at' => $data['paid_at'] ?? now(),
                'amount' => $amount,
                'method' => $data['method'] ?? 'cash',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->recalcTotals($consignment);

            return $payment;
        });
    }

    public function returnItems(Consignment $consignment, array $data): ConsignmentReturn
    {
        $rows = collect($data['items'] ?? [])
            ->filter(fn (array $row) => ! empty($row['consignment_item_id']) && (int) ($row['quantity'] ?? 0) > 0)
            ->values();

        if ($rows->isEmpty()) {
            throw new InvalidArgumentException('Indica al menos una cantidad a devolver.');
        }

        return DB::transaction(function () use ($consignment, $data, $rows) {
            $consignment = Consignment::query()->lockForUpdate()->findOrFail($consignment->id);

            if ($consignment->isVoided()) {
                throw new RuntimeException('No se puede devolver una consignación anulada.');
            }

            $return = ConsignmentReturn::query()->create([
                'consignment_id' => $consignment->id,
                'returned_at' => $data['returned_at'] ?? now(),
                'total_with_vat' => 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $returnTotal = 0.0;
            $restorePayload = [];

            foreach ($rows as $row) {
                $item = ConsignmentItem::query()
                    ->where('consignment_id', $consignment->id)
                    ->lockForUpdate()
                    ->findOrFail($row['consignment_item_id']);

                $qty = (int) $row['quantity'];
                $available = $item->quantityOutstanding();
                if ($qty > $available) {
                    throw new InvalidArgumentException(
                        "No puedes devolver más de {$available} und. del producto #{$item->product_id}."
                    );
                }

                $lineTotal = round($qty * (float) $item->unit_price_with_vat, 2);
                $return->items()->create([
                    'consignment_item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_price_with_vat' => $item->unit_price_with_vat,
                    'line_total_with_vat' => $lineTotal,
                ]);

                $remainingToReturn = $qty;
                $allocations = $item->lotAllocations()
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($allocations as $allocation) {
                    if ($remainingToReturn <= 0) {
                        break;
                    }
                    $allocAvailable = $allocation->quantityOutstanding();
                    if ($allocAvailable <= 0) {
                        continue;
                    }
                    $take = min($allocAvailable, $remainingToReturn);
                    $allocation->quantity_returned += $take;
                    $allocation->save();

                    if ($allocation->inventory_lot_id !== null) {
                        $restorePayload[] = [
                            'lot_id' => $allocation->inventory_lot_id,
                            'quantity' => $take,
                        ];
                    }
                    $remainingToReturn -= $take;
                }

                if ($remainingToReturn > 0) {
                    throw new RuntimeException('No hay suficiente trazabilidad FIFO para devolver ese stock.');
                }

                $item->quantity_returned += $qty;
                $item->save();
                $returnTotal += $lineTotal;
            }

            if ($restorePayload !== []) {
                $this->fifo->restore($restorePayload, [
                    'type' => 'consignment',
                    'id' => $consignment->id,
                    'occurred_at' => $return->returned_at,
                    'movement_type' => InventoryMovement::TYPE_DEVOLUCION_CONSIGNACION,
                    'notes' => 'Devolución consignación '.$consignment->number,
                ]);
            }

            $return->update(['total_with_vat' => round($returnTotal, 2)]);
            $this->recalcTotals($consignment);

            return $return->fresh('items');
        });
    }

    public function void(Consignment $consignment): Consignment
    {
        return DB::transaction(function () use ($consignment) {
            $consignment = Consignment::query()
                ->with(['items.lotAllocations'])
                ->lockForUpdate()
                ->findOrFail($consignment->id);

            if (! $consignment->canVoid()) {
                throw new RuntimeException('Solo se puede anular si no tiene pagos registrados.');
            }

            if ($consignment->isVoided()) {
                throw new RuntimeException('La consignación ya está anulada.');
            }

            $restorePayload = [];
            foreach ($consignment->items as $item) {
                foreach ($item->lotAllocations as $allocation) {
                    $outstanding = $allocation->quantityOutstanding();
                    if ($outstanding <= 0) {
                        continue;
                    }
                    if ($allocation->inventory_lot_id !== null) {
                        $restorePayload[] = [
                            'lot_id' => $allocation->inventory_lot_id,
                            'quantity' => $outstanding,
                        ];
                    }
                    $allocation->quantity_returned = (int) $allocation->quantity;
                    $allocation->save();
                }
                $item->quantity_returned = (int) $item->quantity;
                $item->save();
            }

            if ($restorePayload !== []) {
                $this->fifo->restore($restorePayload, [
                    'type' => 'consignment',
                    'id' => $consignment->id,
                    'occurred_at' => now(),
                    'movement_type' => InventoryMovement::TYPE_ANULACION_CONSIGNACION,
                    'notes' => 'Anulación consignación '.$consignment->number,
                ]);
            }

            $consignment->update([
                'status' => Consignment::STATUS_VOIDED,
                'voided_at' => now(),
                'returned_with_vat' => $consignment->total_with_vat,
                'balance_with_vat' => 0,
            ]);

            return $consignment->fresh(['seller', 'customer', 'items.product']);
        });
    }

    public function recalcTotals(Consignment $consignment): Consignment
    {
        $consignment->loadMissing(['items', 'payments']);

        $total = round((float) $consignment->items->sum('line_total_with_vat'), 2);
        $returned = round(
            (float) $consignment->items->sum(
                fn (ConsignmentItem $item) => (int) $item->quantity_returned * (float) $item->unit_price_with_vat
            ),
            2
        );
        $paid = round((float) $consignment->payments->sum('amount'), 2);
        $balance = round(max(0, $total - $returned - $paid), 2);

        $status = $consignment->status;
        if ($status !== Consignment::STATUS_VOIDED) {
            if ($balance <= 0.009) {
                $status = Consignment::STATUS_SETTLED;
            } elseif ($paid > 0.009) {
                $status = Consignment::STATUS_PARTIAL;
            } else {
                $status = Consignment::STATUS_OPEN;
            }
        }

        $consignment->update([
            'total_with_vat' => $total,
            'returned_with_vat' => $returned,
            'paid_with_vat' => $paid,
            'balance_with_vat' => $balance,
            'status' => $status,
        ]);

        return $consignment->fresh([
            'seller',
            'customer',
            'items.product',
            'payments',
            'returns.items',
        ]);
    }

    public function nextNumber(): string
    {
        $prefix = 'CON-'.now()->format('Ymd').'-';
        $last = Consignment::query()
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
