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

        $salesQuery = Sale::query()
            ->revenue()
            ->whereBetween('sold_at', [$from, $to]);

        $sales = (clone $salesQuery)->get([
            'taxable_base',
            'vat_amount',
            'cogs_total',
            'gross_margin',
            'total',
            'shipping_amount',
            'carrier_shipping_cost',
            'carrier_commission_amount',
        ]);

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

        $netWithoutVat = round($realMarginWithoutVat - $expensesTotal - $returnsLoss, 2);
        $netWithVat = round($realMarginWithVat - $expensesTotal - $returnsLoss, 2);

        return [
            'from' => $from,
            'to' => $to,
            'sales_count' => $sales->count(),
            'sales_total' => $salesTotal,
            'sales_total_with_vat' => $salesTotalWithVat,
            'vat_total' => $vatTotal,
            'cogs_total' => $cogsTotal,
            'shipping_charged' => $shippingCharged,
            'carrier_cost_total' => $carrierCostTotal,
            'shipping_net' => $shippingNet,
            'gross_margin' => $grossMarginWithoutVat,
            'gross_margin_without_vat' => $grossMarginWithoutVat,
            'gross_margin_with_vat' => $grossMarginWithVat,
            'real_margin_without_vat' => $realMarginWithoutVat,
            'real_margin_with_vat' => $realMarginWithVat,
            'expenses_total' => round($expensesTotal, 2),
            'returns_count' => $returnsCount,
            'returns_rate' => $returnsRate,
            'returns_shipping_cost' => $returnsShippingCost,
            'returns_loss' => $returnsLoss,
            'net_result' => $netWithoutVat,
            'net_result_with_vat' => $netWithVat,
            'gross_margin_percent' => $salesTotal > 0 ? round(($grossMarginWithoutVat / $salesTotal) * 100, 2) : 0.0,
            'gross_margin_percent_with_vat' => $salesTotalWithVat > 0
                ? round(($grossMarginWithVat / $salesTotalWithVat) * 100, 2)
                : 0.0,
            'real_margin_percent_with_vat' => ($salesTotalWithVat + $shippingCharged) > 0
                ? round(($realMarginWithVat / ($salesTotalWithVat + $shippingCharged)) * 100, 2)
                : 0.0,
            'supplier_payables_balance' => $this->supplierPayablesBalance(),
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
}
