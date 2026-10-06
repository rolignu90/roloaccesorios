<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Logistics\StoreLogisticsSettlementRequest;
use App\Models\LogisticsClient;
use App\Models\LogisticsSettlement;
use App\Services\LogisticsSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class LogisticsSettlementController extends Controller
{
    public function __construct(
        private LogisticsSettlementService $settlements,
    ) {}

    public function index(Request $request): View
    {
        $settlements = LogisticsSettlement::query()
            ->with('client:id,code,name')
            ->when($request->filled('client_id'), fn ($q) => $q->where('logistics_client_id', $request->integer('client_id')))
            ->latest('paid_at')
            ->paginate(20)
            ->withQueryString();

        return view('logistics.settlements.index', [
            'settlements' => $settlements,
            'clients' => LogisticsClient::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $clients = LogisticsClient::query()->where('is_active', true)->orderBy('name')->get();
        $clientId = $request->integer('logistics_client_id') ?: null;
        $periodType = $request->input('period_type', LogisticsSettlement::PERIOD_WEEK);
        if (! in_array($periodType, [
            LogisticsSettlement::PERIOD_WEEK,
            LogisticsSettlement::PERIOD_MONTH,
            LogisticsSettlement::PERIOD_CUSTOM,
        ], true)) {
            $periodType = LogisticsSettlement::PERIOD_WEEK;
        }

        $anchorDate = $request->input('anchor_date', now()->toDateString());
        $from = $request->input('from');
        $to = $request->input('to');
        $preview = null;
        $error = null;

        if ($clientId && $request->has('logistics_client_id')) {
            try {
                $period = $this->settlements->resolvePeriod(
                    $periodType,
                    $anchorDate,
                    $from,
                    $to,
                );
                $client = LogisticsClient::query()->findOrFail($clientId);
                $preview = $this->settlements->preview(
                    $client,
                    $periodType,
                    $period['from'],
                    $period['to'],
                );
                $from = $period['from']->toDateString();
                $to = $period['to']->toDateString();
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('logistics.settlements.create', [
            'clients' => $clients,
            'clientId' => $clientId,
            'periodType' => $periodType,
            'anchorDate' => $anchorDate,
            'from' => $from,
            'to' => $to,
            'preview' => $preview,
            'error' => $error,
        ]);
    }

    public function store(StoreLogisticsSettlementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $client = LogisticsClient::query()->findOrFail((int) $data['logistics_client_id']);

        try {
            $settlement = $this->settlements->settle($client, $data);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('logistics.settlements.show', $settlement)
            ->with('success', "Liquidación {$settlement->number} registrada. A pagar a la empresa: ".money($settlement->amount_due));
    }

    public function show(LogisticsSettlement $settlement): View
    {
        $settlement->load(['client', 'items.shipment', 'createdBy:id,name', 'voidedBy:id,name']);

        return view('logistics.settlements.show', compact('settlement'));
    }

    public function void(LogisticsSettlement $settlement): RedirectResponse
    {
        try {
            $this->settlements->void($settlement);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('logistics.settlements.show', $settlement)
            ->with('success', 'Liquidación anulada. Los envíos vuelven a estar disponibles para liquidar.');
    }
}
