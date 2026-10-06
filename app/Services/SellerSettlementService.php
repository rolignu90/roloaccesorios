<?php

namespace App\Services;

use App\Models\Consignment;
use App\Models\Sale;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Models\SellerSettlementItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SellerSettlementService
{
    public function __construct(
        private ConsignmentService $consignments,
    ) {}

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public function resolvePeriod(string $periodType, ?string $anchorDate = null, ?string $from = null, ?string $to = null): array
    {
        if ($periodType === SellerSettlement::PERIOD_CUSTOM) {
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

        if ($periodType === SellerSettlement::PERIOD_WEEK) {
            return [
                'from' => $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                'to' => $anchor->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
            ];
        }

        if ($periodType === SellerSettlement::PERIOD_MONTH) {
            return [
                'from' => $anchor->copy()->startOfMonth()->startOfDay(),
                'to' => $anchor->copy()->endOfMonth()->endOfDay(),
            ];
        }

        throw new InvalidArgumentException('Tipo de período inválido.');
    }

    /**
     * Open consignment debt for the seller + linked customer (if any).
     *
     * @return array{
     *   balance: float,
     *   consignments: Collection<int, Consignment>,
     *   customer_id: int|null
     * }
     */
    public function linkedConsignmentDebt(Seller $seller): array
    {
        $seller->loadMissing('customer');
        $customerId = $seller->customer_id ? (int) $seller->customer_id : null;

        $rows = collect();
        if ($customerId) {
            $rows = $rows->concat(
                $this->consignments->openConsignmentsForParty(Consignment::PARTY_CUSTOMER, $customerId)
            );
        }
        $rows = $rows->concat(
            $this->consignments->openConsignmentsForParty(Consignment::PARTY_SELLER, (int) $seller->id)
        );

        $rows = $rows->unique('id')->sortBy([
            ['delivered_at', 'asc'],
            ['id', 'asc'],
        ])->values();

        return [
            'balance' => round((float) $rows->sum('balance_with_vat'), 2),
            'consignments' => $rows,
            'customer_id' => $customerId,
        ];
    }

    /**
     * Preview / calculate settlement without saving.
     *
     * @return array<string, mixed>
     */
    public function preview(
        Seller $seller,
        string $periodType,
        Carbon $from,
        Carbon $to,
        ?float $salaryOverride = null,
        ?float $commissionPercentOverride = null,
        ?float $applyToConsignments = null,
    ): array {
        $sales = $this->unsettleRevenueSalesQuery($seller->id, $from, $to)->with('customer')->orderBy('sold_at')->get();
        $returns = $this->unsettleReturnsQuery($seller->id, $from, $to)->with('customer')->orderBy('sold_at')->get();

        $salesTotal = round((float) $sales->sum('total'), 2);
        $salesCogs = round((float) $sales->sum('cogs_total'), 2);
        $salesMargin = round((float) $sales->sum(fn (Sale $sale) => $sale->realMarginWithVat()), 2);

        $returnsCogs = round((float) $returns->sum('cogs_total'), 2);
        $returnsCarrier = round((float) $returns->sum(fn (Sale $sale) => $sale->carrierCostTotal()), 2);
        $returnsCost = round($returnsCogs + $returnsCarrier, 2);

        $salary = 0.0;
        $commissionPercent = null;
        $commissionBase = 0.0;
        $commissionAmount = 0.0;
        $amountDue = 0.0;
        $formula = '';

        if ($seller->isExternal()) {
            $amountDue = round($salesMargin - $returnsCost, 2);
            $formula = 'Margen real c/IVA de ventas − (COGS devoluciones + costo envío/COD de devoluciones)';
        } else {
            $salary = $salaryOverride !== null
                ? round($salaryOverride, 2)
                : round((float) ($seller->salary_amount ?? 0), 2);
            $commissionPercent = $commissionPercentOverride !== null
                ? round($commissionPercentOverride, 2)
                : ($seller->commission_percent !== null ? round((float) $seller->commission_percent, 2) : 0.0);
            $commissionBase = $salesTotal;
            $commissionAmount = round($commissionBase * ((float) $commissionPercent / 100), 2);
            $amountDue = round($salary + $commissionAmount, 2);
            $formula = 'Salario del período + comisión % sobre total de ventas c/IVA';
        }

        $debt = $this->linkedConsignmentDebt($seller);
        $maxApply = max(0, min($amountDue, $debt['balance']));

        // Por defecto: aplicar TODO lo posible a consignaciones automáticamente.
        // Solo si el usuario manda un monto explícito (incluido 0) se respeta.
        if ($applyToConsignments === null) {
            $applied = $maxApply;
        } else {
            $requestedApply = round($applyToConsignments, 2);
            if ($requestedApply < 0) {
                $requestedApply = 0.0;
            }
            if ($requestedApply > $maxApply + 0.009) {
                throw new InvalidArgumentException(
                    'No puedes aplicar más de '.money($maxApply).' a consignaciones (mínimo entre lo adeudado al vendedor y el saldo de consignación).'
                );
            }
            $applied = round(min($requestedApply, $maxApply), 2);
        }

        $cashPaid = round(max(0, $amountDue - $applied), 2);
        $plannedAllocations = $this->consignments->planAmountFifo(
            $seller->customer_id ? (int) $seller->customer_id : null,
            (int) $seller->id,
            $applied
        );

        return [
            'seller' => $seller,
            'period_type' => $periodType,
            'period_from' => $from->copy()->startOfDay(),
            'period_to' => $to->copy()->startOfDay(),
            'sales' => $sales,
            'returns' => $returns,
            'sales_count' => $sales->count(),
            'returns_count' => $returns->count(),
            'sales_total' => $salesTotal,
            'sales_cogs' => $salesCogs,
            'sales_real_margin_with_vat' => $salesMargin,
            'returns_cogs' => $returnsCogs,
            'returns_carrier_cost' => $returnsCarrier,
            'returns_cost_total' => $returnsCost,
            'salary_amount' => $salary,
            'commission_percent' => $commissionPercent,
            'commission_base' => $commissionBase,
            'commission_amount' => $commissionAmount,
            'amount_due' => $amountDue,
            'formula' => $formula,
            'linked_customer' => $seller->customer,
            'consignment_balance' => $debt['balance'],
            'open_consignments' => $debt['consignments'],
            'max_apply_to_consignments' => $maxApply,
            'applied_to_consignments' => $applied,
            'cash_paid' => $cashPaid,
            'planned_allocations' => $plannedAllocations,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function settle(Seller $seller, array $data): SellerSettlement
    {
        $from = Carbon::parse($data['period_from'])->startOfDay();
        $to = Carbon::parse($data['period_to'])->endOfDay();

        $apply = array_key_exists('apply_to_consignments', $data) && $data['apply_to_consignments'] !== null && $data['apply_to_consignments'] !== ''
            ? (float) $data['apply_to_consignments']
            : null;

        $preview = $this->preview(
            $seller,
            $data['period_type'],
            $from,
            $to,
            array_key_exists('salary_amount', $data) && $data['salary_amount'] !== null && $data['salary_amount'] !== ''
                ? (float) $data['salary_amount']
                : null,
            array_key_exists('commission_percent', $data) && $data['commission_percent'] !== null && $data['commission_percent'] !== ''
                ? (float) $data['commission_percent']
                : null,
            $apply,
        );

        if ($preview['sales_count'] === 0 && $preview['returns_count'] === 0 && abs($preview['amount_due']) < 0.01) {
            throw new InvalidArgumentException('No hay ventas ni devoluciones pendientes para liquidar en ese período.');
        }

        return DB::transaction(function () use ($seller, $data, $preview, $from, $to) {
            $saleIds = $preview['sales']->pluck('id')->all();
            $returnIds = $preview['returns']->pluck('id')->all();
            $allIds = array_values(array_unique(array_merge($saleIds, $returnIds)));

            if ($allIds !== []) {
                $already = SellerSettlementItem::query()
                    ->whereIn('sale_id', $allIds)
                    ->whereHas('settlement', fn ($q) => $q->where('status', '!=', SellerSettlement::STATUS_VOIDED))
                    ->exists();
                if ($already) {
                    throw new RuntimeException('Alguna venta/devolución ya fue liquidada. Recarga e intenta de nuevo.');
                }
            }

            $settlement = SellerSettlement::query()->create([
                'number' => $this->nextNumber(),
                'seller_id' => $seller->id,
                'linked_customer_id' => $seller->customer_id,
                'seller_type' => $seller->type,
                'period_type' => $data['period_type'],
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
                'sales_count' => $preview['sales_count'],
                'returns_count' => $preview['returns_count'],
                'sales_total' => $preview['sales_total'],
                'sales_cogs' => $preview['sales_cogs'],
                'sales_real_margin_with_vat' => $preview['sales_real_margin_with_vat'],
                'returns_cogs' => $preview['returns_cogs'],
                'returns_carrier_cost' => $preview['returns_carrier_cost'],
                'returns_cost_total' => $preview['returns_cost_total'],
                'salary_amount' => $preview['salary_amount'],
                'commission_percent' => $preview['commission_percent'],
                'commission_base' => $preview['commission_base'],
                'commission_amount' => $preview['commission_amount'],
                'amount_due' => $preview['amount_due'],
                'applied_to_consignments' => $preview['applied_to_consignments'],
                'cash_paid' => $preview['cash_paid'],
                'status' => SellerSettlement::STATUS_PAID,
                'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($preview['sales'] as $sale) {
                SellerSettlementItem::query()->create([
                    'seller_settlement_id' => $settlement->id,
                    'sale_id' => $sale->id,
                    'item_type' => SellerSettlementItem::TYPE_SALE,
                    'sale_total' => $sale->total,
                    'cogs_total' => $sale->cogs_total,
                    'real_margin_with_vat' => $sale->realMarginWithVat(),
                    'carrier_cost_total' => $sale->carrierCostTotal(),
                ]);
            }

            foreach ($preview['returns'] as $sale) {
                SellerSettlementItem::query()->create([
                    'seller_settlement_id' => $settlement->id,
                    'sale_id' => $sale->id,
                    'item_type' => SellerSettlementItem::TYPE_RETURN,
                    'sale_total' => $sale->total,
                    'cogs_total' => $sale->cogs_total,
                    'real_margin_with_vat' => $sale->realMarginWithVat(),
                    'carrier_cost_total' => $sale->carrierCostTotal(),
                ]);
            }

            if ($preview['applied_to_consignments'] > 0.009) {
                $this->consignments->applyAmountFifo(
                    $seller->customer_id ? (int) $seller->customer_id : null,
                    (int) $seller->id,
                    (float) $preview['applied_to_consignments'],
                    [
                        'paid_at' => $settlement->paid_at,
                        'method' => 'seller_settlement',
                        'notes' => 'Compensación liquidación '.$settlement->number,
                        'seller_settlement_id' => $settlement->id,
                    ]
                );
            }

            return $settlement->fresh(['seller.customer', 'linkedCustomer', 'items.sale.customer', 'consignmentPayments.consignment']);
        });
    }

    public function void(SellerSettlement $settlement): SellerSettlement
    {
        if ($settlement->isVoided()) {
            throw new InvalidArgumentException('Esta liquidación ya está anulada.');
        }

        return DB::transaction(function () use ($settlement) {
            $this->consignments->reverseSellerSettlementPayments((int) $settlement->id);

            $settlement->update([
                'status' => SellerSettlement::STATUS_VOIDED,
            ]);

            return $settlement->fresh(['seller.customer', 'linkedCustomer', 'items.sale.customer', 'consignmentPayments.consignment']);
        });
    }

    public function nextNumber(): string
    {
        $prefix = 'LIQ-'.now()->format('Ymd').'-';
        $max = SellerSettlement::query()
            ->where('number', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(number, '.(strlen($prefix) + 1).') AS UNSIGNED)) as max_seq')
            ->value('max_seq');

        return $prefix.str_pad((string) (((int) $max) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function unsettleRevenueSalesQuery(int $sellerId, Carbon $from, Carbon $to): Builder
    {
        // Solo entregadas: confirmadas / en ruta no entran a la liquidación.
        return Sale::query()
            ->where('seller_id', $sellerId)
            ->where('status', Sale::STATUS_DELIVERED)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('status_changed_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->whereNull('status_changed_at')
                            ->whereBetween('sold_at', [$from, $to]);
                    });
            })
            ->whereDoesntHave('settlementItems', function ($q) {
                $q->where('item_type', SellerSettlementItem::TYPE_SALE)
                    ->whereHas('settlement', fn ($s) => $s->where('status', '!=', SellerSettlement::STATUS_VOIDED));
            });
    }

    private function unsettleReturnsQuery(int $sellerId, Carbon $from, Carbon $to): Builder
    {
        return Sale::query()
            ->where('seller_id', $sellerId)
            ->where('status', Sale::STATUS_RETURNED)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('status_changed_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->whereNull('status_changed_at')
                            ->whereBetween('sold_at', [$from, $to]);
                    });
            })
            ->whereDoesntHave('settlementItems', function ($q) {
                $q->where('item_type', SellerSettlementItem::TYPE_RETURN)
                    ->whereHas('settlement', fn ($s) => $s->where('status', '!=', SellerSettlement::STATUS_VOIDED));
            });
    }
}
