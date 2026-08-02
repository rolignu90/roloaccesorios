<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\AddSaleItemRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Requests\Sales\UpdateSaleCustomerRequest;
use App\Http\Requests\Sales\UpdateSaleItemRequest;
use App\Http\Requests\Sales\UpdateSaleShippingRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Seller;
use App\Models\ShippingCarrier;
use App\Services\CustomerPricingService;
use App\Services\SaleLabelExportService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SaleController extends Controller
{
    public function __construct(
        private SaleService $sales,
        private SaleLabelExportService $labelExport,
        private CustomerPricingService $customerPricing,
    ) {
    }

    public function index(Request $request): View|JsonResponse
    {
        $sales = Sale::query()
            ->with(['customer', 'seller'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('number', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('seller', fn ($s) => $s->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('seller_id'), fn ($q) => $q->where('seller_id', $request->integer('seller_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('sold_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('sold_at', '<=', $request->date('to')))
            ->when($request->filled('shipping'), function ($query) use ($request) {
                $shipping = $request->string('shipping')->toString();
                if ($shipping === 'with') {
                    $query->where('has_shipping', true);
                } elseif ($shipping === 'without') {
                    $query->where('has_shipping', false);
                }
            })
            ->latest('sold_at')
            ->paginate(20)
            ->withQueryString();

        if ($request->boolean('infinite') || $request->ajax()) {
            return response()->json([
                'html' => view('sales.sales._rows', [
                    'sales' => $sales,
                    'omitEmpty' => true,
                ])->render(),
                'next_page_url' => $sales->nextPageUrl()
                    ? $sales->nextPageUrl().(str_contains($sales->nextPageUrl() ?? '', '?') ? '&' : '?').'infinite=1'
                    : null,
                'has_more' => $sales->hasMorePages(),
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'total' => $sales->total(),
            ]);
        }

        $sellers = Seller::query()->orderBy('name')->get(['id', 'code', 'name']);

        return view('sales.sales.index', compact('sales', 'sellers'));
    }

    public function create(): View
    {
        $customers = Customer::query()->where('is_active', true)->orderBy('name')->get();
        $sellers = Seller::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Seller $seller) {
                $seller->setAttribute('next_sale_number', $this->sales->nextNumber($seller));

                return $seller;
            });
        $products = Product::query()
            ->where('is_active', true)
            ->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')
            ->orderBy('name')
            ->get();

        $selectedSellerId = old('seller_id', request('seller_id'));
        $nextNumber = $sellers->firstWhere('id', (int) $selectedSellerId)?->next_sale_number
            ?? $sellers->first()?->next_sale_number
            ?? $this->sales->nextNumber();

        $productPriceDefaults = $products->mapWithKeys(function (Product $product) {
            return [
                (string) $product->id => [
                    'sale' => $product->effectiveSalePriceWithVat(),
                    'wholesale' => $product->wholesalePriceWithVat(),
                ],
            ];
        })->all();

        $shippingCarriers = ShippingCarrier::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return view('sales.sales.create', [
            'customers' => $customers,
            'sellers' => $sellers,
            'products' => $products,
            'shippingCarriers' => $shippingCarriers,
            'defaultShippingCarrierId' => $shippingCarriers->first()?->id,
            'paymentMethods' => config('sales.payment_methods'),
            'vatRate' => config('sales.vat_rate'),
            'defaultShipping' => config('sales.default_shipping_amount', 3),
            'nextNumber' => $nextNumber,
            'customerPriceTiers' => $this->customerPricing->catalogForJs(),
            'productPriceDefaults' => $productPriceDefaults,
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        try {
            $sale = DB::transaction(function () use ($request) {
                $data = $request->validated();

                if (($data['customer_mode'] ?? 'new') === 'new') {
                    $newCustomer = $data['new_customer'] ?? [];
                    $customer = Customer::createWithNextCode([
                        'name' => $newCustomer['name'],
                        'document_type' => $newCustomer['document_type'] ?? 'N/A',
                        'document_number' => (($newCustomer['document_type'] ?? 'N/A') === 'N/A')
                            ? null
                            : ($newCustomer['document_number'] ?? null),
                        'email' => $newCustomer['email'] ?? null,
                        'phone' => $newCustomer['phone'] ?? null,
                        'address' => $newCustomer['address'] ?? null,
                        'department' => $newCustomer['department'] ?? null,
                        'municipality' => $newCustomer['municipality'] ?? null,
                        'country' => $newCustomer['country'] ?? 'El Salvador',
                        'postal_code' => $newCustomer['postal_code'] ?? null,
                        'notes' => $newCustomer['notes'] ?? null,
                        'is_active' => true,
                    ]);
                    $data['customer_id'] = $customer->id;
                }

                unset($data['customer_mode'], $data['new_customer']);

                return $this->sales->create($data);
            });
        } catch (Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['sale' => $e->getMessage()]);
        }

        $message = "Venta {$sale->number} registrada. Stock descontado por FIFO.";

        if ($request->input('after_save') === 'new') {
            return redirect()
                ->route('sales.sales.create', array_filter([
                    'seller_id' => $sale->seller_id,
                    'payment_method' => $sale->payment_method,
                ]))
                ->with('success', $message.' Puedes registrar la siguiente.');
        }

        return redirect()
            ->route('sales.sales.show', $sale)
            ->with('success', $message);
    }

    public function show(Sale $sale): View
    {
        $sale->load([
            'customer',
            'seller',
            'shippingCarrier',
            'items.product',
            'items.lotAllocations.inventoryLot',
        ]);

        $products = collect();
        if ($sale->isConfirmed()) {
            $products = Product::query()
                ->where('is_active', true)
                ->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')
                ->orderBy('name')
                ->get();
        }

        return view('sales.sales.show', compact('sale', 'products'));
    }

    public function updateCustomer(UpdateSaleCustomerRequest $request, Sale $sale): RedirectResponse
    {
        if (! $sale->isConfirmed()) {
            return back()->withErrors(['sale' => 'Solo se pueden editar ventas confirmadas.']);
        }

        $customer = $sale->customer;
        if (! $customer) {
            return back()->withErrors(['sale' => 'La venta no tiene cliente asociado.']);
        }

        $customer->update($request->validated());

        return redirect()
            ->route('sales.sales.show', $sale)
            ->with('success', 'Datos del cliente / entrega actualizados.');
    }

    public function addItem(AddSaleItemRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->sales->addItem($sale, $request->validated());
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['sale' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales.sales.show', $sale)
            ->with('success', 'Producto agregado. Totales y stock actualizados.');
    }

    public function updateItem(UpdateSaleItemRequest $request, Sale $sale, SaleItem $item): RedirectResponse
    {
        try {
            $this->sales->updateItemQuantity($sale, $item, (int) $request->validated('quantity'));
        } catch (InvalidArgumentException|RuntimeException|Throwable $e) {
            return back()->withInput()->withErrors(['sale' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales.sales.show', $sale)
            ->with('success', 'Cantidad actualizada. Totales y stock recalculados.');
    }

    public function destroyItem(Sale $sale, SaleItem $item): RedirectResponse
    {
        try {
            $this->sales->removeItem($sale, $item);
        } catch (Throwable $e) {
            return back()->withErrors(['sale' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales.sales.show', $sale)
            ->with('success', 'Producto quitado. Stock restaurado y totales recalculados.');
    }

    public function updateShipping(UpdateSaleShippingRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->sales->updateShipping($sale, $request->validated());
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['sale' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales.sales.show', $sale)
            ->with('success', 'Envío / notas actualizados. Comisión COD recalculada.');
    }

    public function void(Sale $sale): RedirectResponse
    {
        try {
            $this->sales->void($sale);
        } catch (RuntimeException $e) {
            return back()->withErrors(['sale' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales.sales.show', $sale)
            ->with('success', 'Venta anulada y stock restaurado.');
    }

    public function exportLabels(Request $request): StreamedResponse|RedirectResponse
    {
        if ($request->filled('sale_ids')) {
            $request->validate([
                'sale_ids' => ['required', 'array', 'min:1'],
                'sale_ids.*' => ['integer', 'exists:sales,id'],
            ]);

            $rows = $this->labelExport->rowsForIds($request->input('sale_ids', []));

            if ($rows->isEmpty()) {
                return back()->withErrors([
                    'export' => 'Ninguna de las ventas seleccionadas es exportable (deben estar confirmadas y con envío).',
                ]);
            }

            return $this->labelExport->downloadCsvForIds($request->input('sale_ids', []));
        }

        $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $request->filled('date')
            ? Carbon::parse($request->string('date'))
            : now();

        $rows = $this->labelExport->rowsForDate($date);

        if ($rows->isEmpty()) {
            return back()->withErrors([
                'export' => 'No hay ventas confirmadas con envío para '.$date->format('d/m/Y').'.',
            ]);
        }

        return $this->labelExport->downloadCsvForDate($date);
    }
}
