<?php

namespace App\Services;

use App\Models\Expense;
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
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereBetween('sold_at', [$from, $to]);

        $salesTotal = (float) (clone $salesQuery)->sum('taxable_base');
        $vatTotal = (float) (clone $salesQuery)->sum('vat_amount');
        $cogsTotal = (float) (clone $salesQuery)->sum('cogs_total');
        $salesTotalWithVat = round($salesTotal + $vatTotal, 2);
        $grossMarginWithoutVat = round($salesTotal - $cogsTotal, 2);
        $grossMarginWithVat = round($salesTotalWithVat - $cogsTotal, 2);

        $expensesTotal = (float) Expense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        return [
            'from' => $from,
            'to' => $to,
            'sales_count' => (clone $salesQuery)->count(),
            'sales_total' => round($salesTotal, 2),
            'sales_total_with_vat' => $salesTotalWithVat,
            'vat_total' => round($vatTotal, 2),
            'cogs_total' => round($cogsTotal, 2),
            'gross_margin' => $grossMarginWithoutVat,
            'gross_margin_without_vat' => $grossMarginWithoutVat,
            'gross_margin_with_vat' => $grossMarginWithVat,
            'expenses_total' => round($expensesTotal, 2),
            'net_result' => round($grossMarginWithoutVat - $expensesTotal, 2),
            'net_result_with_vat' => round($grossMarginWithVat - $expensesTotal, 2),
            'gross_margin_percent' => $salesTotal > 0 ? round(($grossMarginWithoutVat / $salesTotal) * 100, 2) : 0.0,
            'gross_margin_percent_with_vat' => $salesTotalWithVat > 0
                ? round(($grossMarginWithVat / $salesTotalWithVat) * 100, 2)
                : 0.0,
        ];
    }

    public function marginsBySale(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        return Sale::query()
            ->with('customer')
            ->where('status', Sale::STATUS_CONFIRMED)
            ->whereBetween('sold_at', [$from, $to])
            ->orderByDesc('sold_at')
            ->get();
    }

    public function marginsByProduct(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        return SaleItem::query()
            ->selectRaw('
                product_id,
                SUM(quantity) as qty_sold,
                SUM(line_subtotal) as sales_total,
                SUM(line_total) as sales_total_with_vat,
                SUM(cogs_total) as cogs_total,
                SUM(line_subtotal - cogs_total) as gross_margin,
                SUM(line_total - cogs_total) as gross_margin_with_vat
            ')
            ->whereHas('sale', function ($query) use ($from, $to) {
                $query->where('status', Sale::STATUS_CONFIRMED)
                    ->whereBetween('sold_at', [$from, $to]);
            })
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('gross_margin')
            ->get();
    }
}
