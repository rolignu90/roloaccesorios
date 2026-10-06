<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSellerSettlementRequest;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Services\SellerSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SellerSettlementController extends Controller
{
    public function __construct(
        private SellerSettlementService $settlements,
    ) {}

    public function index(Request $request): View
    {
        $settlements = SellerSettlement::query()
            ->with('seller')
            ->when($request->filled('seller_id'), fn ($q) => $q->where('seller_id', $request->integer('seller_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('paid_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $sellers = Seller::query()->orderBy('name')->get(['id', 'code', 'name']);

        return view('sales.seller-settlements.index', compact('settlements', 'sellers'));
    }

    public function create(Request $request): View
    {
        $sellers = Seller::query()->where('is_active', true)->orderBy('name')->get();
        $sellerId = $request->filled('seller_id') ? $request->integer('seller_id') : null;
        $periodType = $request->input('period_type', SellerSettlement::PERIOD_WEEK);
        if (! in_array($periodType, [
            SellerSettlement::PERIOD_WEEK,
            SellerSettlement::PERIOD_MONTH,
            SellerSettlement::PERIOD_CUSTOM,
        ], true)) {
            $periodType = SellerSettlement::PERIOD_WEEK;
        }

        $preview = null;
        $error = null;
        $seller = $sellerId ? Seller::query()->find($sellerId) : null;

        if ($seller) {
            try {
                $period = $this->settlements->resolvePeriod(
                    $periodType,
                    $request->input('anchor_date'),
                    $request->input('from'),
                    $request->input('to'),
                );
                $preview = $this->settlements->preview(
                    $seller,
                    $periodType,
                    $period['from'],
                    $period['to'],
                    $request->filled('salary_amount') ? (float) $request->input('salary_amount') : null,
                    $request->filled('commission_percent') ? (float) $request->input('commission_percent') : null,
                    $request->filled('apply_to_consignments') ? (float) $request->input('apply_to_consignments') : null,
                );
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        return view('sales.seller-settlements.create', [
            'sellers' => $sellers,
            'sellerId' => $sellerId,
            'periodType' => $periodType,
            'anchorDate' => $request->input('anchor_date', now()->toDateString()),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'preview' => $preview,
            'error' => $error,
            'paymentMethods' => config('sales.payment_methods', []),
        ]);
    }

    public function store(StoreSellerSettlementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $seller = Seller::query()->findOrFail($data['seller_id']);

        try {
            $period = $this->settlements->resolvePeriod(
                $data['period_type'],
                $data['anchor_date'] ?? null,
                $data['from'] ?? null,
                $data['to'] ?? null,
            );

            $settlement = $this->settlements->settle($seller, [
                'period_type' => $data['period_type'],
                'period_from' => $period['from'],
                'period_to' => $period['to'],
                'salary_amount' => $data['salary_amount'] ?? null,
                'commission_percent' => $data['commission_percent'] ?? null,
                'apply_to_consignments' => $data['apply_to_consignments'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['settle' => $e->getMessage()]);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['settle' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales.seller-settlements.show', $settlement)
            ->with('success', "Liquidación {$settlement->number} registrada. A pagar: ".money($settlement->amount_due));
    }

    public function show(SellerSettlement $sellerSettlement): View
    {
        $sellerSettlement->load([
            'seller.customer',
            'linkedCustomer',
            'items.sale.customer',
            'consignmentPayments.consignment',
        ]);

        return view('sales.seller-settlements.show', [
            'settlement' => $sellerSettlement,
        ]);
    }

    public function void(SellerSettlement $sellerSettlement): RedirectResponse
    {
        try {
            $this->settlements->void($sellerSettlement);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['settle' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales.seller-settlements.show', $sellerSettlement)
            ->with('success', 'Liquidación anulada. Las ventas vuelven a estar disponibles para liquidar.');
    }
}
