<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Unidades vendidas por producto. Los combos ya se guardan desglosados por producto en
 * sale_items (combo_id marca la línea), así que sumar sale_items.quantity da unidades reales.
 * Las anuladas no cuentan; las devoluciones se reportan aparte y no suman como vendidas.
 */
class ProductSalesReportService
{
    public const PERIODS = [
        'all' => 'Todo',
        'today' => 'Hoy',
        '7d' => 'Últimos 7 días',
        '30d' => 'Últimos 30 días',
        'month' => 'Este mes',
        'last_month' => 'Mes pasado',
        'year' => 'Este año',
        'custom' => 'Rango',
    ];

    public const SORTS = [
        'units' => 'Más vendidos',
        'revenue' => 'Mayor ingreso',
        'returns' => 'Más devoluciones',
        'stock' => 'Menor stock',
        'name' => 'Nombre',
    ];

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public function range(string $period, ?string $from = null, ?string $to = null): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '30d' => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
            'month' => [$now->startOfMonth(), $now->endOfDay()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'year' => [$now->startOfYear(), $now->endOfDay()],
            'custom' => [
                $from ? CarbonImmutable::parse($from)->startOfDay() : null,
                $to ? CarbonImmutable::parse($to)->endOfDay() : null,
            ],
            default => [null, null],
        };
    }

    /**
     * @param  array{from: ?CarbonImmutable, to: ?CarbonImmutable, channel: ?string, store_id: ?int}  $filters
     */
    public function totalsSubquery(array $filters): QueryBuilder
    {
        $returned = DB::getPdo()->quote(Sale::STATUS_RETURNED);

        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', '!=', Sale::STATUS_VOIDED)
            ->when($filters['from'], fn ($q, $from) => $q->where('sales.sold_at', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->where('sales.sold_at', '<=', $to))
            ->when($filters['channel'], fn ($q, $channel) => $q->where('sales.channel', $channel))
            ->when($filters['store_id'], fn ($q, $storeId) => $q->where('sales.store_id', $storeId))
            ->groupBy('sale_items.product_id')
            ->selectRaw("
                sale_items.product_id,
                SUM(CASE WHEN sales.status <> {$returned} THEN sale_items.quantity ELSE 0 END) AS units_sold,
                SUM(CASE WHEN sales.status <> {$returned} AND sale_items.combo_id IS NOT NULL THEN sale_items.quantity ELSE 0 END) AS units_in_combos,
                SUM(CASE WHEN sales.status = {$returned} THEN sale_items.quantity ELSE 0 END) AS units_returned,
                COUNT(DISTINCT CASE WHEN sales.status <> {$returned} THEN sales.id END) AS sales_count,
                SUM(CASE WHEN sales.status <> {$returned} THEN sale_items.line_total ELSE 0 END) AS revenue,
                SUM(CASE WHEN sales.status <> {$returned} THEN sale_items.cogs_total ELSE 0 END) AS cogs,
                MAX(CASE WHEN sales.status <> {$returned} THEN sales.sold_at END) AS last_sold_at
            ");
    }

    /**
     * @param  array{from: ?CarbonImmutable, to: ?CarbonImmutable, channel: ?string, store_id: ?int, q: ?string, include_unsold: bool, sort: string}  $filters
     */
    public function query(array $filters): Builder
    {
        $stock = DB::table('inventory_lots')
            ->selectRaw('product_id, SUM(quantity_remaining) AS stock_on_hand')
            ->groupBy('product_id');

        $query = Product::query()
            ->select('products.id', 'products.code', 'products.name', 'products.unit', 'products.is_active', 'products.on_demand')
            ->selectRaw('COALESCE(t.units_sold, 0) AS units_sold, COALESCE(t.units_in_combos, 0) AS units_in_combos')
            ->selectRaw('COALESCE(t.units_returned, 0) AS units_returned, COALESCE(t.sales_count, 0) AS sales_count')
            ->selectRaw('COALESCE(t.revenue, 0) AS revenue, COALESCE(t.cogs, 0) AS cogs, t.last_sold_at')
            ->selectRaw('COALESCE(s.stock_on_hand, 0) AS stock_on_hand')
            ->leftJoinSub($this->totalsSubquery($filters), 't', 't.product_id', '=', 'products.id')
            ->leftJoinSub($stock, 's', 's.product_id', '=', 'products.id')
            ->when(! $filters['include_unsold'], fn ($q) => $q->where(fn ($w) => $w->where('t.units_sold', '>', 0)->orWhere('t.units_returned', '>', 0)))
            ->when($filters['q'], function ($q, $term) {
                $q->where(fn ($w) => $w->where('products.code', 'like', "%{$term}%")->orWhere('products.name', 'like', "%{$term}%"));
            });

        return match ($filters['sort']) {
            'revenue' => $query->orderByDesc('revenue')->orderBy('products.name'),
            'returns' => $query->orderByDesc('units_returned')->orderByDesc('units_sold'),
            'stock' => $query->orderBy('stock_on_hand')->orderByDesc('units_sold'),
            'name' => $query->orderBy('products.name'),
            default => $query->orderByDesc('units_sold')->orderBy('products.name'),
        };
    }

    /**
     * @return array{products: int, units_sold: int, units_in_combos: int, units_returned: int, revenue: float, cogs: float}
     */
    public function totals(array $filters): array
    {
        $row = DB::query()->fromSub($this->query($filters)->reorder()->toBase(), 'r')
            ->selectRaw('COUNT(*) AS products, SUM(units_sold) AS units_sold, SUM(units_in_combos) AS units_in_combos')
            ->selectRaw('SUM(units_returned) AS units_returned, SUM(revenue) AS revenue, SUM(cogs) AS cogs')
            ->first();

        return [
            'products' => (int) ($row->products ?? 0),
            'units_sold' => (int) ($row->units_sold ?? 0),
            'units_in_combos' => (int) ($row->units_in_combos ?? 0),
            'units_returned' => (int) ($row->units_returned ?? 0),
            'revenue' => (float) ($row->revenue ?? 0),
            'cogs' => (float) ($row->cogs ?? 0),
        ];
    }
}
