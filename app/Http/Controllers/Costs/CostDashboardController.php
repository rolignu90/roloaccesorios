<?php

namespace App\Http\Controllers\Costs;

use App\Http\Controllers\Controller;
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

        return view('costs.dashboard.index', compact('summary', 'salesMargins', 'productMargins', 'from', 'to'));
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
}
