<?php

namespace App\Http\Controllers\Costs;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\CostReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CostDashboardController extends Controller
{
    public function __construct(private CostReportService $reports) {}

    public function index(Request $request): View
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : now()->endOfDay();

        $summary = $this->reports->summary($from, $to);
        $salesMargins = $this->reports->marginsBySale($from, $to)->take(15);
        $productMargins = $this->reports->marginsByProduct($from, $to)->take(15);
        $returns = $this->reports->returnsInPeriod($from, $to)->take(20);

        return view('costs.dashboard.index', compact(
            'summary',
            'salesMargins',
            'productMargins',
            'returns',
            'from',
            'to'
        ));
    }

    public function margins(Request $request): View
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : now()->endOfDay();

        $salesMargins = $this->reports->marginsBySale($from, $to);
        $productMargins = $this->reports->marginsByProduct($from, $to);

        return view('costs.margins.index', compact('salesMargins', 'productMargins', 'from', 'to'));
    }

    public function bySeller(Request $request): View
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : now()->endOfDay();

        $sellerId = $request->filled('seller_id') ? (int) $request->input('seller_id') : null;

        $bySeller = $this->reports->salesBySeller($from, $to, $sellerId);
        $sales = $this->reports->salesForSellerReport($from, $to, $sellerId);
        $sellers = Seller::query()->orderBy('name')->get(['id', 'code', 'name']);

        $totals = (object) [
            'sales_count' => (int) $bySeller->sum('sales_count'),
            'sales_total' => round((float) $bySeller->sum('sales_total'), 2),
            'sales_total_with_vat' => round((float) $bySeller->sum('sales_total_with_vat'), 2),
            'shipping_charged' => round((float) $bySeller->sum('shipping_charged'), 2),
            'carrier_cost_total' => round((float) $bySeller->sum('carrier_cost_total'), 2),
            'shipping_net' => round((float) $bySeller->sum('shipping_net'), 2),
            'cogs_total' => round((float) $bySeller->sum('cogs_total'), 2),
            'gross_margin_with_vat' => round((float) $bySeller->sum('gross_margin_with_vat'), 2),
            'real_margin_with_vat' => round((float) $bySeller->sum('real_margin_with_vat'), 2),
        ];

        return view('costs.sellers.index', compact(
            'bySeller',
            'sales',
            'sellers',
            'totals',
            'from',
            'to',
            'sellerId',
        ));
    }

    public function byPeriod(Request $request): View
    {
        $groupBy = $request->string('group')->toString();
        if (! in_array($groupBy, ['day', 'week', 'month'], true)) {
            $groupBy = 'day';
        }

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : match ($groupBy) {
                'week' => now()->subWeeks(11)->startOfWeek(Carbon::MONDAY),
                'month' => now()->subMonths(11)->startOfMonth(),
                default => now()->startOfMonth(),
            };
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : now()->endOfDay();

        $rows = $this->reports->salesByPeriod($groupBy, $from, $to);

        // Siempre mostrar comparación mensual (mín. 12 meses hacia atrás desde "hasta").
        $monthlyTo = $to->copy()->endOfMonth();
        $monthlyFrom = $from->copy()->startOfMonth();
        if ($monthlyFrom->diffInMonths($monthlyTo) < 11) {
            $monthlyFrom = $monthlyTo->copy()->subMonths(11)->startOfMonth();
        }
        $monthlyRows = $this->reports->salesByPeriod('month', $monthlyFrom, $monthlyTo);

        $bestMonth = $monthlyRows
            ->sortByDesc(fn ($row) => (float) $row->sales_total_with_vat)
            ->first();
        $worstMonth = $monthlyRows
            ->filter(fn ($row) => (float) $row->sales_total_with_vat > 0 || (int) $row->sales_count > 0)
            ->sortBy(fn ($row) => (float) $row->sales_total_with_vat)
            ->first()
            ?? $monthlyRows->sortBy(fn ($row) => (float) $row->sales_total_with_vat)->first();

        $totals = (object) [
            'sales_count' => (int) $rows->sum('sales_count'),
            'sales_total' => round((float) $rows->sum('sales_total'), 2),
            'sales_total_with_vat' => round((float) $rows->sum('sales_total_with_vat'), 2),
            'cogs_total' => round((float) $rows->sum('cogs_total'), 2),
            'real_margin_with_vat' => round((float) $rows->sum('real_margin_with_vat'), 2),
            'realized_margin_with_vat' => round((float) $rows->sum('realized_margin_with_vat'), 2),
            'pending_sales_count' => (int) $rows->sum('pending_sales_count'),
            'pending_margin_with_vat' => round((float) $rows->sum('pending_margin_with_vat'), 2),
            'returns_count' => (int) $rows->sum('returns_count'),
            'returns_loss' => round((float) $rows->sum('returns_loss'), 2),
            'expenses_total' => round((float) $rows->sum('expenses_total'), 2),
            'net_result_with_vat' => round((float) $rows->sum('net_result_with_vat'), 2),
            'net_result_realized_with_vat' => round((float) $rows->sum('net_result_realized_with_vat'), 2),
        ];

        return view('costs.periods.index', compact(
            'rows',
            'totals',
            'from',
            'to',
            'groupBy',
            'monthlyRows',
            'monthlyFrom',
            'monthlyTo',
            'bestMonth',
            'worstMonth',
        ));
    }
}
