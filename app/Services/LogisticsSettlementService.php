<?php

namespace App\Services;

use App\Models\LogisticsClient;
use App\Models\LogisticsSettlement;
use App\Models\LogisticsSettlementItem;
use App\Models\LogisticsShipment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class LogisticsSettlementService
{
    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public function resolvePeriod(string $periodType, ?string $anchorDate = null, ?string $from = null, ?string $to = null): array
    {
        if ($periodType === LogisticsSettlement::PERIOD_CUSTOM) {
            if (! $from || ! $to) {
                throw new InvalidArgumentException('Indica fecha desde y hasta para el período personalizado.');
            }
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();
            if ($end->lt($start)) {
                throw new InvalidArgumentException('La fecha hasta no puede ser anterior a desde.');
            }

            return ['from' => $start, 'to' => $end];
        }

        $anchor = $anchorDate ? Carbon::parse($anchorDate) : now();

        if ($periodType === LogisticsSettlement::PERIOD_WEEK) {
            return [
                'from' => $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                'to' => $anchor->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
            ];
        }

        if ($periodType === LogisticsSettlement::PERIOD_MONTH) {
            return [
                'from' => $anchor->copy()->startOfMonth()->startOfDay(),
                'to' => $anchor->copy()->endOfMonth()->endOfDay(),
            ];
        }

        throw new InvalidArgumentException('Tipo de período inválido.');
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(
        LogisticsClient $client,
        string $periodType,
        Carbon $from,
        Carbon $to,
    ): array {
        $shipments = $this->unsettleDeliveredQuery($client->id, $from, $to)
            ->orderBy('shipped_at')
            ->get();
        $returns = $this->unsettleReturnsQuery($client->id, $from, $to)
            ->orderBy('shipped_at')
            ->get();

        $collectTotal = round((float) $shipments->sum('collect_amount'), 2);
        $deliveredShipping = round((float) $shipments->sum('carrier_shipping_cost'), 2);
        $returnShipping = round((float) $returns->sum('carrier_shipping_cost'), 2);
        $carrierCommission = round((float) $shipments->sum('carrier_commission_amount'), 2);
        $serviceCommission = round((float) $shipments->sum('service_commission'), 2);
        $deliveredDue = round((float) $shipments->sum('payable_to_client'), 2);
        // Negative = the client owes us (returned freight exceeds delivered COD).
        $amountDue = round($deliveredDue - $returnShipping, 2);

        return [
            'client' => $client,
            'period_type' => $periodType,
            'period_from' => $from->copy()->startOfDay(),
            'period_to' => $to->copy()->startOfDay(),
            'shipments' => $shipments,
            'returns' => $returns,
            'shipments_count' => $shipments->count(),
            'returns_count' => $returns->count(),
            'collect_total' => $collectTotal,
            'carrier_shipping_total' => round($deliveredShipping + $returnShipping, 2),
            'delivered_shipping_total' => $deliveredShipping,
            'return_shipping_total' => $returnShipping,
            'carrier_commission_total' => $carrierCommission,
            'service_commission_total' => $serviceCommission,
            'delivered_due' => $deliveredDue,
            'amount_due' => $amountDue,
            'formula' => 'COD entregados − comisión Sistrack − flete − tu comisión − flete perdido en devoluciones',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function settle(LogisticsClient $client, array $data): LogisticsSettlement
    {
        $from = Carbon::parse($data['period_from'])->startOfDay();
        $to = Carbon::parse($data['period_to'])->endOfDay();

        $preview = $this->preview($client, $data['period_type'], $from, $to);

        if ($preview['shipments_count'] === 0 && $preview['returns_count'] === 0) {
            throw new InvalidArgumentException('No hay envíos entregados ni devoluciones pendientes para liquidar en ese período.');
        }

        return DB::transaction(function () use ($client, $data, $preview, $from, $to) {
            $shipmentIds = $preview['shipments']->pluck('id')->all();
            $returnIds = $preview['returns']->pluck('id')->all();
            $allIds = array_values(array_unique(array_merge($shipmentIds, $returnIds)));

            if ($allIds !== []) {
                LogisticsShipment::query()->whereIn('id', $allIds)->lockForUpdate()->get(['id']);

                $already = LogisticsSettlementItem::query()
                    ->whereIn('logistics_shipment_id', $allIds)
                    ->whereHas('settlement', fn ($q) => $q->where('status', '!=', LogisticsSettlement::STATUS_VOIDED))
                    ->exists();
                if ($already) {
                    throw new RuntimeException('Algún envío ya fue liquidado. Recarga e intenta de nuevo.');
                }
            }

            $settlement = LogisticsSettlement::query()->create([
                'number' => $this->nextNumber(),
                'logistics_client_id' => $client->id,
                'period_type' => $data['period_type'],
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'shipments_count' => $preview['shipments_count'],
                'returns_count' => $preview['returns_count'],
                'collect_total' => $preview['collect_total'],
                'carrier_shipping_total' => $preview['carrier_shipping_total'],
                'carrier_commission_total' => $preview['carrier_commission_total'],
                'service_commission_total' => $preview['service_commission_total'],
                'amount_due' => $preview['amount_due'],
                'status' => LogisticsSettlement::STATUS_PAID,
                'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => auth()->id(),
            ]);

            foreach ($preview['shipments'] as $shipment) {
                LogisticsSettlementItem::query()->create([
                    'logistics_settlement_id' => $settlement->id,
                    'logistics_shipment_id' => $shipment->id,
                    'item_type' => LogisticsSettlementItem::TYPE_SHIPMENT,
                    'collect_amount' => $shipment->collect_amount,
                    'carrier_shipping_cost' => $shipment->carrier_shipping_cost,
                    'carrier_commission_amount' => $shipment->carrier_commission_amount,
                    'service_commission' => $shipment->service_commission,
                    'payable_to_client' => $shipment->payable_to_client,
                ]);
            }

            foreach ($preview['returns'] as $shipment) {
                LogisticsSettlementItem::query()->create([
                    'logistics_settlement_id' => $settlement->id,
                    'logistics_shipment_id' => $shipment->id,
                    'item_type' => LogisticsSettlementItem::TYPE_RETURN,
                    'collect_amount' => $shipment->collect_amount,
                    'carrier_shipping_cost' => $shipment->carrier_shipping_cost,
                    'carrier_commission_amount' => $shipment->carrier_commission_amount,
                    'service_commission' => $shipment->service_commission,
                    'payable_to_client' => -round((float) $shipment->carrier_shipping_cost, 2),
                ]);
            }

            return $settlement->fresh(['client', 'items.shipment']);
        });
    }

    public function void(LogisticsSettlement $settlement): LogisticsSettlement
    {
        if ($settlement->isVoided()) {
            throw new InvalidArgumentException('Esta liquidación ya está anulada.');
        }

        $settlement->update([
            'status' => LogisticsSettlement::STATUS_VOIDED,
            'voided_by_user_id' => auth()->id(),
        ]);

        return $settlement->fresh(['client', 'items.shipment']);
    }

    public function nextNumber(): string
    {
        $prefix = 'LIQENV-'.now()->format('Ymd').'-';
        $max = LogisticsSettlement::query()
            ->where('number', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(number, '.(strlen($prefix) + 1).') AS UNSIGNED)) as max_seq')
            ->value('max_seq');

        return $prefix.str_pad((string) (((int) $max) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function unsettleDeliveredQuery(int $clientId, Carbon $from, Carbon $to): Builder
    {
        return LogisticsShipment::query()
            ->where('logistics_client_id', $clientId)
            ->where('status', LogisticsShipment::STATUS_DELIVERED)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('status_changed_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->whereNull('status_changed_at')
                            ->whereBetween('shipped_at', [$from, $to]);
                    });
            })
            ->whereDoesntHave('settlementItems', function ($q) {
                $q->where('item_type', LogisticsSettlementItem::TYPE_SHIPMENT)
                    ->whereHas('settlement', fn ($s) => $s->where('status', '!=', LogisticsSettlement::STATUS_VOIDED));
            });
    }

    private function unsettleReturnsQuery(int $clientId, Carbon $from, Carbon $to): Builder
    {
        return LogisticsShipment::query()
            ->where('logistics_client_id', $clientId)
            ->where('status', LogisticsShipment::STATUS_RETURNED)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('status_changed_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->whereNull('status_changed_at')
                            ->whereBetween('shipped_at', [$from, $to]);
                    });
            })
            ->whereDoesntHave('settlementItems', function ($q) {
                $q->where('item_type', LogisticsSettlementItem::TYPE_RETURN)
                    ->whereHas('settlement', fn ($s) => $s->where('status', '!=', LogisticsSettlement::STATUS_VOIDED));
            });
    }
}
