<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\InventoryLot;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CostReportService
{
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        $sales = Sale::query()
            ->revenue()
            ->whereBetween('sold_at', [$from, $to])
            ->get([
                'id',
                'status',
                'taxable_base',
                'vat_amount',
                'cogs_total',
                'gross_margin',
                'total',
                'shipping_amount',
                'carrier_shipping_cost',
                'carrier_commission_amount',
            ]);

        $delivered = $sales->where('status', Sale::STATUS_DELIVERED)->values();
        $pending = $sales->whereIn('status', [
            Sale::STATUS_CONFIRMED,
            Sale::STATUS_IN_TRANSIT,
        ])->values();

        $expected = $this->marginSnapshot($sales);
        $realized = $this->marginSnapshot($delivered);
        $pipeline = $this->marginSnapshot($pending);

        $expensesTotal = (float) Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $returns = Sale::query()
            ->where('status', Sale::STATUS_RETURNED)
            ->whereBetween('sold_at', [$from, $to])
            ->get([
                'id',
                'total',
                'shipping_amount',
                'carrier_shipping_cost',
                'carrier_commission_amount',
            ]);

        $returnsCount = $returns->count();
        // Pérdida por devolución = solo flete del método de envío (sin comisión COD).
        $returnsShippingCost = round((float) $returns->sum('carrier_shipping_cost'), 2);
        $returnsLoss = $returnsShippingCost;
        $attemptedCount = $sales->count() + $returnsCount;
        $returnsRate = $attemptedCount > 0
            ? round(($returnsCount / $attemptedCount) * 100, 2)
            : 0.0;

        $expectedNetWithoutVat = round($expected['real_margin_without_vat'] - $expensesTotal - $returnsLoss, 2);
        $expectedNetWithVat = round($expected['real_margin_with_vat'] - $expensesTotal - $returnsLoss, 2);
        $realizedNetWithoutVat = round($realized['real_margin_without_vat'] - $expensesTotal - $returnsLoss, 2);
        $realizedNetWithVat = round($realized['real_margin_with_vat'] - $expensesTotal - $returnsLoss, 2);

        return [
            'from' => $from,
            'to' => $to,
            // Compat: campos actuales = resultado esperado (si todo en ruta se entrega).
            'sales_count' => $expected['sales_count'],
            'sales_total' => $expected['sales_total'],
            'sales_total_with_vat' => $expected['sales_total_with_vat'],
            'vat_total' => $expected['vat_total'],
            'cogs_total' => $expected['cogs_total'],
            'shipping_charged' => $expected['shipping_charged'],
            'carrier_cost_total' => $expected['carrier_cost_total'],
            'shipping_net' => $expected['shipping_net'],
            'gross_margin' => $expected['gross_margin_without_vat'],
            'gross_margin_without_vat' => $expected['gross_margin_without_vat'],
            'gross_margin_with_vat' => $expected['gross_margin_with_vat'],
            'real_margin_without_vat' => $expected['real_margin_without_vat'],
            'real_margin_with_vat' => $expected['real_margin_with_vat'],
            'expenses_total' => round($expensesTotal, 2),
            'returns_count' => $returnsCount,
            'returns_rate' => $returnsRate,
            'returns_shipping_cost' => $returnsShippingCost,
            'returns_loss' => $returnsLoss,
            'net_result' => $expectedNetWithoutVat,
            'net_result_with_vat' => $expectedNetWithVat,
            'gross_margin_percent' => $expected['sales_total'] > 0
                ? round(($expected['gross_margin_without_vat'] / $expected['sales_total']) * 100, 2)
                : 0.0,
            'gross_margin_percent_with_vat' => $expected['sales_total_with_vat'] > 0
                ? round(($expected['gross_margin_with_vat'] / $expected['sales_total_with_vat']) * 100, 2)
                : 0.0,
            'real_margin_percent_with_vat' => ($expected['sales_total_with_vat'] + $expected['shipping_charged']) > 0
                ? round(($expected['real_margin_with_vat'] / ($expected['sales_total_with_vat'] + $expected['shipping_charged'])) * 100, 2)
                : 0.0,
            'supplier_payables_balance' => $this->supplierPayablesBalance(),

            // Esperado vs realizado (tiempo real).
            'expected' => array_merge($expected, [
                'net_result' => $expectedNetWithoutVat,
                'net_result_with_vat' => $expectedNetWithVat,
            ]),
            'realized' => array_merge($realized, [
                'net_result' => $realizedNetWithoutVat,
                'net_result_with_vat' => $realizedNetWithVat,
                'net_result_percent_with_vat' => ($realized['sales_total_with_vat'] + $realized['shipping_charged']) > 0
                    ? round(($realizedNetWithVat / ($realized['sales_total_with_vat'] + $realized['shipping_charged'])) * 100, 2)
                    : 0.0,
            ]),
            'pending' => $pipeline,
            'gap_expected_vs_realized_with_vat' => round($expectedNetWithVat - $realizedNetWithVat, 2),
        ];
    }

    /**
     * Totales de margen a partir de un set de ventas.
     *
     * @param  Collection<int, Sale>  $sales
     * @return array{
     *   sales_count: int,
     *   sales_total: float,
     *   sales_total_with_vat: float,
     *   vat_total: float,
     *   cogs_total: float,
     *   shipping_charged: float,
     *   carrier_cost_total: float,
     *   shipping_net: float,
     *   gross_margin_without_vat: float,
     *   gross_margin_with_vat: float,
     *   real_margin_without_vat: float,
     *   real_margin_with_vat: float
     * }
     */
    private function marginSnapshot(Collection $sales): array
    {
        $salesTotal = round((float) $sales->sum('taxable_base'), 2);
        $vatTotal = round((float) $sales->sum('vat_amount'), 2);
        $cogsTotal = round((float) $sales->sum('cogs_total'), 2);
        $salesTotalWithVat = round($salesTotal + $vatTotal, 2);
        $shippingCharged = round((float) $sales->sum('shipping_amount'), 2);
        $carrierCostTotal = round((float) $sales->sum(fn (Sale $sale) => $sale->carrierCostTotal()), 2);
        $shippingNet = round($shippingCharged - $carrierCostTotal, 2);

        $grossMarginWithoutVat = round($salesTotal - $cogsTotal, 2);
        $grossMarginWithVat = round($salesTotalWithVat - $cogsTotal, 2);
        $realMarginWithoutVat = round($grossMarginWithoutVat + $shippingNet, 2);
        $realMarginWithVat = round($grossMarginWithVat + $shippingNet, 2);

        return [
            'sales_count' => $sales->count(),
            'sales_total' => $salesTotal,
            'sales_total_with_vat' => $salesTotalWithVat,
            'vat_total' => $vatTotal,
            'cogs_total' => $cogsTotal,
            'shipping_charged' => $shippingCharged,
            'carrier_cost_total' => $carrierCostTotal,
            'shipping_net' => $shippingNet,
            'gross_margin_without_vat' => $grossMarginWithoutVat,
            'gross_margin_with_vat' => $grossMarginWithVat,
            'real_margin_without_vat' => $realMarginWithoutVat,
            'real_margin_with_vat' => $realMarginWithVat,
        ];
    }

    /**
     * Devoluciones del período (pérdida = flete del método de envío, sin COD).
     *
     * @return Collection<int, Sale>
     */
    public function returnsInPeriod(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        return Sale::query()
            ->with(['customer', 'shippingCarrier'])
            ->where('status', Sale::STATUS_RETURNED)
            ->whereBetween('sold_at', [$from, $to])
            ->orderByDesc('sold_at')
            ->get();
    }

    /** Saldo adeudado a proveedores (todas las compras / no filtrado por período). */
    public function supplierPayablesBalance(): float
    {
        return round((float) InventoryLot::query()
            ->where('quantity_received', '>', 0)
            ->get()
            ->sum(fn (InventoryLot $lot) => $lot->balanceDue()), 2);
    }

    public function marginsBySale(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        return Sale::query()
            ->with('customer')
            ->revenue()
            ->whereBetween('sold_at', [$from, $to])
            ->orderByDesc('sold_at')
            ->get();
    }

    public function marginsByProduct(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        $items = SaleItem::query()
            ->with(['product', 'sale'])
            ->whereHas('sale', function ($query) use ($from, $to) {
                $query->revenue()
                    ->whereBetween('sold_at', [$from, $to]);
            })
            ->get();

        $byProduct = [];

        foreach ($items->groupBy('sale_id') as $saleItems) {
            /** @var Sale|null $sale */
            $sale = $saleItems->first()?->sale;
            if (! $sale) {
                continue;
            }

            $shippingNet = round(
                (float) $sale->shipping_amount - $sale->carrierCostTotal(),
                2
            );
            $saleLineTotal = round((float) $saleItems->sum('line_total'), 2);
            $allocatedShipping = 0.0;
            $ordered = $saleItems->values();
            $lastIndex = $ordered->count() - 1;

            foreach ($ordered as $index => $item) {
                $lineTotal = (float) $item->line_total;
                $lineSubtotal = (float) $item->line_subtotal;
                $cogs = (float) $item->cogs_total;

                if ($index === $lastIndex) {
                    $shippingShare = round($shippingNet - $allocatedShipping, 2);
                } elseif ($saleLineTotal > 0) {
                    $shippingShare = round($shippingNet * ($lineTotal / $saleLineTotal), 2);
                    $allocatedShipping = round($allocatedShipping + $shippingShare, 2);
                } else {
                    $shippingShare = 0.0;
                }

                $productId = (int) $item->product_id;
                if (! isset($byProduct[$productId])) {
                    $byProduct[$productId] = [
                        'product_id' => $productId,
                        'product' => $item->product,
                        'qty_sold' => 0,
                        'sales_total' => 0.0,
                        'sales_total_with_vat' => 0.0,
                        'cogs_total' => 0.0,
                        'gross_margin' => 0.0,
                        'gross_margin_with_vat' => 0.0,
                        'shipping_net' => 0.0,
                        'real_margin' => 0.0,
                        'real_margin_with_vat' => 0.0,
                    ];
                }

                $grossWithoutVat = round($lineSubtotal - $cogs, 2);
                $grossWithVat = round($lineTotal - $cogs, 2);

                $byProduct[$productId]['qty_sold'] += (int) $item->quantity;
                $byProduct[$productId]['sales_total'] = round($byProduct[$productId]['sales_total'] + $lineSubtotal, 2);
                $byProduct[$productId]['sales_total_with_vat'] = round($byProduct[$productId]['sales_total_with_vat'] + $lineTotal, 2);
                $byProduct[$productId]['cogs_total'] = round($byProduct[$productId]['cogs_total'] + $cogs, 2);
                $byProduct[$productId]['gross_margin'] = round($byProduct[$productId]['gross_margin'] + $grossWithoutVat, 2);
                $byProduct[$productId]['gross_margin_with_vat'] = round($byProduct[$productId]['gross_margin_with_vat'] + $grossWithVat, 2);
                $byProduct[$productId]['shipping_net'] = round($byProduct[$productId]['shipping_net'] + $shippingShare, 2);
                $byProduct[$productId]['real_margin'] = round($byProduct[$productId]['real_margin'] + $grossWithoutVat + $shippingShare, 2);
                $byProduct[$productId]['real_margin_with_vat'] = round($byProduct[$productId]['real_margin_with_vat'] + $grossWithVat + $shippingShare, 2);
            }
        }

        return collect($byProduct)
            ->map(fn (array $row) => (object) $row)
            ->sortByDesc('real_margin_with_vat')
            ->values();
    }

    /**
     * Resumen de ventas confirmadas agrupadas por vendedor.
     *
     * @return Collection<int, object{
     *     seller_id: int|null,
     *     seller_code: string,
     *     seller_name: string,
     *     sales_count: int,
     *     sales_total: float,
     *     sales_total_with_vat: float,
     *     shipping_charged: float,
     *     carrier_cost_total: float,
     *     shipping_net: float,
     *     cogs_total: float,
     *     gross_margin_with_vat: float,
     *     real_margin_with_vat: float,
     *     real_margin_percent_with_vat: float
     * }>
     */
    public function salesBySeller(?Carbon $from = null, ?Carbon $to = null, ?int $sellerId = null): Collection
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        $sales = Sale::query()
            ->with('seller')
            ->revenue()
            ->whereBetween('sold_at', [$from, $to])
            ->when($sellerId, fn ($query) => $query->where('seller_id', $sellerId))
            ->orderByDesc('sold_at')
            ->get();

        return $sales
            ->groupBy(fn (Sale $sale) => $sale->seller_id ?: 0)
            ->map(function (Collection $group) {
                /** @var Sale $first */
                $first = $group->first();
                $seller = $first->seller;

                $salesTotal = round((float) $group->sum('taxable_base'), 2);
                $salesTotalWithVat = round((float) $group->sum(fn (Sale $sale) => $sale->productsTotalWithVat()), 2);
                $shippingCharged = round((float) $group->sum('shipping_amount'), 2);
                $carrierCostTotal = round((float) $group->sum(fn (Sale $sale) => $sale->carrierCostTotal()), 2);
                $shippingNet = round($shippingCharged - $carrierCostTotal, 2);
                $cogsTotal = round((float) $group->sum('cogs_total'), 2);
                $grossMarginWithVat = round($salesTotalWithVat - $cogsTotal, 2);
                $realMarginWithVat = round((float) $group->sum(fn (Sale $sale) => $sale->realMarginWithVat()), 2);
                $denominator = $salesTotalWithVat + $shippingCharged;

                return (object) [
                    'seller_id' => $seller?->id,
                    'seller_code' => $seller?->code ?? '—',
                    'seller_name' => $seller?->name ?? 'Sin vendedor',
                    'sales_count' => $group->count(),
                    'sales_total' => $salesTotal,
                    'sales_total_with_vat' => $salesTotalWithVat,
                    'shipping_charged' => $shippingCharged,
                    'carrier_cost_total' => $carrierCostTotal,
                    'shipping_net' => $shippingNet,
                    'cogs_total' => $cogsTotal,
                    'gross_margin_with_vat' => $grossMarginWithVat,
                    'real_margin_with_vat' => $realMarginWithVat,
                    'real_margin_percent_with_vat' => $denominator > 0
                        ? round(($realMarginWithVat / $denominator) * 100, 2)
                        : 0.0,
                ];
            })
            ->sortByDesc('real_margin_with_vat')
            ->values();
    }

    /**
     * Ventas individuales para el detalle del reporte por vendedor.
     */
    public function salesForSellerReport(?Carbon $from = null, ?Carbon $to = null, ?int $sellerId = null): Collection
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        return Sale::query()
            ->with(['customer', 'seller'])
            ->revenue()
            ->whereBetween('sold_at', [$from, $to])
            ->when($sellerId, fn ($query) => $query->where('seller_id', $sellerId))
            ->orderByDesc('sold_at')
            ->get();
    }

    /**
     * Ventas y ganancias agrupadas por día, semana o mes.
     *
     * @param  'day'|'week'|'month'  $groupBy
     * @return Collection<int, object{
     *   key: string,
     *   label: string,
     *   period_start: string,
     *   period_end: string,
     *   sales_count: int,
     *   sales_total: float,
     *   sales_total_with_vat: float,
     *   shipping_charged: float,
     *   carrier_cost_total: float,
     *   cogs_total: float,
     *   gross_margin_with_vat: float,
     *   real_margin_with_vat: float,
     *   returns_count: int,
     *   returns_loss: float,
     *   expenses_total: float,
     *   net_result_with_vat: float
     * }>
     */
    public function salesByPeriod(string $groupBy = 'day', ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $groupBy = in_array($groupBy, ['day', 'week', 'month'], true) ? $groupBy : 'day';
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        $sales = Sale::query()
            ->revenue()
            ->whereBetween('sold_at', [$from, $to])
            ->get([
                'id',
                'status',
                'sold_at',
                'taxable_base',
                'total',
                'shipping_amount',
                'cogs_total',
                'carrier_shipping_cost',
                'carrier_commission_amount',
            ]);

        $returns = Sale::query()
            ->where('status', Sale::STATUS_RETURNED)
            ->whereBetween('sold_at', [$from, $to])
            ->get(['id', 'sold_at', 'carrier_shipping_cost']);

        $expenses = Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->get(['id', 'expense_date', 'amount']);

        $buckets = [];

        $ensure = function (string $key, Carbon $start, Carbon $end, string $label) use (&$buckets): void {
            if (isset($buckets[$key])) {
                return;
            }
            $buckets[$key] = [
                'key' => $key,
                'label' => $label,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'sort' => $start->timestamp,
                'sales_count' => 0,
                'sales_total' => 0.0,
                'sales_total_with_vat' => 0.0,
                'shipping_charged' => 0.0,
                'carrier_cost_total' => 0.0,
                'cogs_total' => 0.0,
                'gross_margin_with_vat' => 0.0,
                'real_margin_with_vat' => 0.0,
                'realized_margin_with_vat' => 0.0,
                'pending_sales_count' => 0,
                'pending_margin_with_vat' => 0.0,
                'returns_count' => 0,
                'returns_loss' => 0.0,
                'expenses_total' => 0.0,
                'net_result_with_vat' => 0.0,
                'net_result_realized_with_vat' => 0.0,
            ];
        };

        // Rellenar períodos vacíos del rango para ver tendencia.
        $cursor = match ($groupBy) {
            'week' => $from->copy()->startOfWeek(Carbon::MONDAY),
            'month' => $from->copy()->startOfMonth(),
            default => $from->copy()->startOfDay(),
        };
        $limit = match ($groupBy) {
            'week' => $to->copy()->startOfWeek(Carbon::MONDAY),
            'month' => $to->copy()->startOfMonth(),
            default => $to->copy()->startOfDay(),
        };

        while ($cursor->lte($limit)) {
            [$key, $start, $end, $label] = $this->periodMeta($cursor, $groupBy);
            $ensure($key, $start, $end, $label);
            $cursor = match ($groupBy) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        foreach ($sales as $sale) {
            $soldAt = Carbon::parse($sale->sold_at);
            [$key, $start, $end, $label] = $this->periodMeta($soldAt, $groupBy);
            $ensure($key, $start, $end, $label);

            $productsWithVat = $sale->productsTotalWithVat();
            $cogs = (float) $sale->cogs_total;
            $shipping = (float) $sale->shipping_amount;
            $carrier = $sale->carrierCostTotal();
            $real = $sale->realMarginWithVat();

            $buckets[$key]['sales_count']++;
            $buckets[$key]['sales_total'] = round($buckets[$key]['sales_total'] + (float) $sale->taxable_base, 2);
            $buckets[$key]['sales_total_with_vat'] = round($buckets[$key]['sales_total_with_vat'] + $productsWithVat, 2);
            $buckets[$key]['shipping_charged'] = round($buckets[$key]['shipping_charged'] + $shipping, 2);
            $buckets[$key]['carrier_cost_total'] = round($buckets[$key]['carrier_cost_total'] + $carrier, 2);
            $buckets[$key]['cogs_total'] = round($buckets[$key]['cogs_total'] + $cogs, 2);
            $buckets[$key]['gross_margin_with_vat'] = round($buckets[$key]['gross_margin_with_vat'] + ($productsWithVat - $cogs), 2);
            $buckets[$key]['real_margin_with_vat'] = round($buckets[$key]['real_margin_with_vat'] + $real, 2);

            if ($sale->status === Sale::STATUS_DELIVERED) {
                $buckets[$key]['realized_margin_with_vat'] = round(
                    $buckets[$key]['realized_margin_with_vat'] + $real,
                    2
                );
            } else {
                $buckets[$key]['pending_sales_count']++;
                $buckets[$key]['pending_margin_with_vat'] = round(
                    $buckets[$key]['pending_margin_with_vat'] + $real,
                    2
                );
            }
        }

        foreach ($returns as $sale) {
            $soldAt = Carbon::parse($sale->sold_at);
            [$key, $start, $end, $label] = $this->periodMeta($soldAt, $groupBy);
            $ensure($key, $start, $end, $label);
            $buckets[$key]['returns_count']++;
            $buckets[$key]['returns_loss'] = round(
                $buckets[$key]['returns_loss'] + (float) $sale->carrier_shipping_cost,
                2
            );
        }

        foreach ($expenses as $expense) {
            $date = Carbon::parse($expense->expense_date)->startOfDay();
            [$key, $start, $end, $label] = $this->periodMeta($date, $groupBy);
            $ensure($key, $start, $end, $label);
            $buckets[$key]['expenses_total'] = round(
                $buckets[$key]['expenses_total'] + (float) $expense->amount,
                2
            );
        }

        return collect($buckets)
            ->map(function (array $row) {
                $row['net_result_with_vat'] = round(
                    $row['real_margin_with_vat'] - $row['expenses_total'] - $row['returns_loss'],
                    2
                );
                $row['net_result_realized_with_vat'] = round(
                    $row['realized_margin_with_vat'] - $row['expenses_total'] - $row['returns_loss'],
                    2
                );

                return (object) $row;
            })
            ->sortBy('sort')
            ->values();
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon, 3: string}
     */
    private function periodMeta(Carbon $date, string $groupBy): array
    {
        return match ($groupBy) {
            'week' => (function () use ($date) {
                $start = $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
                $end = $date->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
                $key = $start->format('o-\WW');
                $label = 'Semana '.$start->format('d/m').' – '.$end->format('d/m/Y');

                return [$key, $start, $end, $label];
            })(),
            'month' => (function () use ($date) {
                $start = $date->copy()->startOfMonth()->startOfDay();
                $end = $date->copy()->endOfMonth()->endOfDay();
                $key = $start->format('Y-m');
                $months = [
                    1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
                    7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
                ];
                $label = ($months[(int) $start->format('n')] ?? $start->format('M')).' '.$start->format('Y');

                return [$key, $start, $end, $label];
            })(),
            default => (function () use ($date) {
                $start = $date->copy()->startOfDay();
                $end = $date->copy()->endOfDay();
                $key = $start->toDateString();
                $label = $start->format('d/m/Y');

                return [$key, $start, $end, $label];
            })(),
        };
    }
}
