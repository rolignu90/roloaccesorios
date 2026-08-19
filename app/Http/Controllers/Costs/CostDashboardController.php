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
    public function __construct(private CostReportService $reports)
    {
    }

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
}
