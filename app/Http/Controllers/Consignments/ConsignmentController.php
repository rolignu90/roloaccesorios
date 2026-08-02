<?php

namespace App\Http\Controllers\Consignments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consignments\StoreConsignmentPaymentRequest;
use App\Http\Requests\Consignments\StoreConsignmentRequest;
use App\Http\Requests\Consignments\StoreConsignmentReturnRequest;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Seller;
use App\Services\ConsignmentDashboardService;
use App\Services\ConsignmentService;
use App\Services\CustomerPricingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ConsignmentController extends Controller
{
    public function __construct(
        private ConsignmentService $consignments,
        private ConsignmentDashboardService $dashboard,
        private CustomerPricingService $customerPricing,
    ) {
    }

    public function dashboard(Request $request): View
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : now()->endOfDay();

        $data = $this->dashboard->snapshot($from, $to);

        return view('consignments.dashboard.index', array_merge($data, compact('from', 'to')));
    }

    public function index(Request $request): View
    {
        $consignments = Consignment::query()
            ->with(['seller', 'customer'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('number', 'like', "%{$term}%")
                        ->orWhereHas('seller', fn ($s) => $s->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('party_type'), fn ($q) => $q->where('party_type', $request->string('party_type')))
            ->latest('delivered_at')
            ->paginate(15)
            ->withQueryString();

        return view('consignments.index', compact('consignments'));
    }

    public function create(): View
    {
        $sellers = Seller::query()->where('is_active', true)->orderBy('name')->get();
        $customers = Customer::query()->where('is_active', true)->orderBy('name')->get();
        $products = Product::query()
            ->where('is_active', true)
            ->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')
            ->orderBy('name')
            ->get();

        $productPriceDefaults = $products->mapWithKeys(function (Product $product) {
            return [
                (string) $product->id => [
                    'sale' => $product->effectiveSalePriceWithVat(),
                    'wholesale' => $product->wholesalePriceWithVat(),
                ],
            ];
        })->all();

        return view('consignments.create', [
            'sellers' => $sellers,
            'customers' => $customers,
            'products' => $products,
            'nextNumber' => $this->consignments->nextNumber(),
            'customerPriceTiers' => $this->customerPricing->catalogForJs(),
            'productPriceDefaults' => $productPriceDefaults,
        ]);
    }

    public function store(StoreConsignmentRequest $request): RedirectResponse
    {
        try {
            $consignment = $this->consignments->create($request->validated());
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['consignment' => $e->getMessage()]);
        }

        return redirect()
            ->route('consignments.show', $consignment)
            ->with('success', "Consignación {$consignment->number} registrada. Stock descontado.");
    }

    public function show(Consignment $consignment): View
    {
        $consignment->load([
            'seller',
            'customer',
            'items.product',
            'payments',
            'returns.items.consignmentItem.product',
        ]);

        return view('consignments.show', [
            'consignment' => $consignment,
            'paymentMethods' => config('sales.payment_methods'),
        ]);
    }

    public function storePayment(StoreConsignmentPaymentRequest $request, Consignment $consignment): RedirectResponse
    {
        try {
            $this->consignments->addPayment($consignment, $request->validated());
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()
            ->route('consignments.show', $consignment)
            ->with('success', 'Pago registrado.');
    }

    public function storeReturn(StoreConsignmentReturnRequest $request, Consignment $consignment): RedirectResponse
    {
        try {
            $this->consignments->returnItems($consignment, $request->validated());
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['return' => $e->getMessage()]);
        }

        return redirect()
            ->route('consignments.show', $consignment)
            ->with('success', 'Devolución registrada y stock restaurado.');
    }

    public function void(Consignment $consignment): RedirectResponse
    {
        try {
            $this->consignments->void($consignment);
        } catch (RuntimeException $e) {
            return back()->withErrors(['consignment' => $e->getMessage()]);
        }

        return redirect()
            ->route('consignments.show', $consignment)
            ->with('success', 'Consignación anulada y stock restaurado.');
    }
}
