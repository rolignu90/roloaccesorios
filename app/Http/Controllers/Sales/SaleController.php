<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\AddSaleItemRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Requests\Sales\UpdateSaleCustomerRequest;
use App\Http\Requests\Sales\UpdateSaleDiscountsRequest;
use App\Http\Requests\Sales\UpdateSaleItemRequest;
use App\Http\Requests\Sales\UpdateSaleSellerRequest;
use App\Http\Requests\Sales\UpdateSaleShippingRequest;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Seller;
use App\Models\Store;
use App\Models\ShippingCarrier;
use App\Services\CustomerPricingService;
use App\Services\SaleLabelExportService;
use App\Services\SaleReportExportService;
use App\Services\SaleService;
use App\Services\SistrackSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        private SaleReportExportService $reportExport,
        private CustomerPricingService $customerPricing,
        private SistrackSyncService $sistrack,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $stuckCount = Sale::query()->visibleTo($request->user())->stuckInTransit(7)->count();
        $pendingSyncCount = Sale::query()->visibleTo($request->user())->pendingSistrackStatusSync()->count();

        $sales = Sale::query()
            ->visibleTo($request->user())
            ->with([
                'customer',
                'seller',
                'shippingCarrier',
                'store:id,name',
                'items.product:id,code,name',
                'items.combo:id,code,name',
            ])
            ->when($request->boolean('stuck_in_transit'), fn ($q) => $q->stuckInTransit(7))
            ->when($request->boolean('pending_sync'), fn ($q) => $q->pendingSistrackStatusSync())
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
            ->when(
                array_key_exists($request->string('channel')->toString(), Sale::CHANNEL_LABELS),
                fn ($q) => $q->where('channel', $request->string('channel')->toString())
            )
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', $request->integer('store_id')))
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
        $stores = Store::query()->orderBy('name')->get(['id', 'name']);

        return view('sales.sales.index', compact('sales', 'sellers', 'stores', 'stuckCount', 'pendingSyncCount'));
    }

    public function create(): View
    {
        $customers = Customer::query()->where('is_active', true)->orderBy('name')->get();
        $customersForPhoneLookup = Customer::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderByDesc('id')
            ->get([
                'id', 'code', 'name', 'document_type', 'document_number',
                'email', 'phone', 'address', 'municipality', 'department',
                'country', 'postal_code', 'notes',
            ]);
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

        $combos = Combo::query()
            ->where('is_active', true)
            ->with(['items.product' => fn ($q) => $q->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')])
            ->orderBy('name')
            ->get()
            ->map(fn (Combo $combo) => $combo->toSaleCatalogEntry())
            ->values();

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

        $quickPickProducts = $this->quickPickProducts($products, $combos);

        $shippingCarriers = ShippingCarrier::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $returnRisk = $this->buildCustomerReturnRiskMap($customers);
        $openSales = $this->buildOpenSalesByPhoneMap();
        $customerPhones = $customers->mapWithKeys(
            fn (Customer $customer) => [(string) $customer->id => $customer->normalizedPhone()]
        )->all();
        $customersByPhone = $this->buildCustomersByPhoneMap($customersForPhoneLookup);

        return view('sales.sales.create', [
            'customers' => $customers,
            'sellers' => $sellers,
            'products' => $products,
            'combos' => $combos,
            'shippingCarriers' => $shippingCarriers,
            'defaultShippingCarrierId' => $shippingCarriers->first()?->id,
            'paymentMethods' => config('sales.payment_methods'),
            'vatRate' => config('sales.vat_rate'),
            'defaultShipping' => config('sales.default_shipping_amount', 3),
            'nextNumber' => $nextNumber,
            'customerPriceTiers' => $this->customerPricing->catalogForJs(),
            'productPriceDefaults' => $productPriceDefaults,
            'quickPickProducts' => $quickPickProducts,
            'returnRiskByCustomer' => $returnRisk['by_customer'],
            'returnRiskByPhone' => $returnRisk['by_phone'],
            'openSalesByCustomer' => $openSales['by_customer'],
            'openSalesByPhone' => $openSales['by_phone'],
            'customerPhones' => $customerPhones,
            'customersByPhone' => $customersByPhone,
        ]);
    }

    /**
     * Favoritos + productos/combos más vendidos (6 meses) para acceso rápido.
     *
     * @param  Collection<int, Product>  $activeProducts
     * @param  Collection<int, array<string, mixed>>  $activeCombos
     * @return Collection<int, object{
     *   value: string,
     *   code: string,
     *   name: string,
     *   kind: string,
     *   qty_sold: ?int
     * }>
     */
    private function quickPickProducts($activeProducts, $activeCombos)
    {
        $productsById = $activeProducts->keyBy('id');
        $combosById = collect($activeCombos)->keyBy(fn ($combo) => (int) ($combo['id'] ?? 0));

        $favorites = $activeProducts
            ->where('is_favorite', true)
            ->sortBy('name')
            ->values()
            ->map(fn (Product $product) => (object) [
                'value' => (string) $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'kind' => 'favorite',
                'qty_sold' => null,
            ]);

        $topProducts = SaleItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as qty_sold'))
            ->whereNotNull('product_id')
            ->whereNull('combo_id')
            ->whereHas('sale', function ($query) {
                $query->where('status', '!=', Sale::STATUS_VOIDED)
                    ->where('sold_at', '>=', now()->subMonths(6));
            })
            ->groupBy('product_id')
            ->orderByDesc('qty_sold')
            ->limit(16)
            ->get();

        $favoriteIds = $favorites->map(fn ($row) => (int) $row->value)->all();
        $topProductPicks = $topProducts
            ->filter(fn ($row) => $productsById->has((int) $row->product_id))
            ->reject(fn ($row) => in_array((int) $row->product_id, $favoriteIds, true))
            ->take(8)
            ->map(function ($row) use ($productsById) {
                $product = $productsById->get((int) $row->product_id);

                return (object) [
                    'value' => (string) $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'kind' => 'top',
                    'qty_sold' => (int) $row->qty_sold,
                ];
            })
            ->values();

        // Combos: cuántas ventas distintas los incluyeron (no suma de componentes).
        $topCombos = SaleItem::query()
            ->select('combo_id', DB::raw('COUNT(DISTINCT sale_id) as sales_count'))
            ->whereNotNull('combo_id')
            ->whereHas('sale', function ($query) {
                $query->where('status', '!=', Sale::STATUS_VOIDED)
                    ->where('sold_at', '>=', now()->subMonths(6));
            })
            ->groupBy('combo_id')
            ->orderByDesc('sales_count')
            ->limit(10)
            ->get();

        $topComboPicks = $topCombos
            ->filter(fn ($row) => $combosById->has((int) $row->combo_id))
            ->take(6)
            ->map(function ($row) use ($combosById) {
                $combo = $combosById->get((int) $row->combo_id);

                return (object) [
                    'value' => 'combo:'.$combo['id'],
                    'code' => $combo['code'] ?? ('C-'.$combo['id']),
                    'name' => $combo['name'] ?? 'Combo',
                    'kind' => 'combo',
                    'qty_sold' => (int) $row->sales_count,
                ];
            })
            ->values();

        return $favorites
            ->concat($topComboPicks)
            ->concat($topProductPicks)
            ->values();
    }

    /**
     * Clientes indexados por teléfono normalizado (últimos 8 dígitos).
     * Si hay varios, se queda el más reciente (updated_at / id).
     *
     * @param  Collection<int, Customer>  $customers
     * @return array<string, array<string, mixed>>
     */
    private function buildCustomersByPhoneMap($customers): array
    {
        $map = [];

        foreach ($customers->sortByDesc('id') as $customer) {
            $phone = $customer->normalizedPhone();
            if (! $phone || isset($map[$phone])) {
                continue;
            }

            $map[$phone] = [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'document_type' => $customer->document_type ?: 'N/A',
                'document_number' => $customer->document_number,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'municipality' => $customer->municipality,
                'department' => $customer->department,
                'country' => $customer->country ?: 'El Salvador',
                'postal_code' => $customer->postal_code,
                'notes' => $customer->notes,
            ];
        }

        return $map;
    }

    /**
     * Ventas abiertas (confirmada / en ruta) por cliente y por teléfono.
     * Sirve para avisar posible venta duplicada al teclear el teléfono.
     *
     * @return array{
     *   by_customer: array<string, array{count:int,sales:list<array{number:string,status:string,status_label:string,sold_at:?string,total:float}>}>,
     *   by_phone: array<string, array{count:int,sales:list<array{number:string,status:string,status_label:string,sold_at:?string,total:float,customer:?string}>}>
     * }
     */
    private function buildOpenSalesByPhoneMap(): array
    {
        $open = Sale::query()
            ->whereIn('status', [Sale::STATUS_CONFIRMED, Sale::STATUS_IN_TRANSIT])
            ->with(['customer:id,name,phone,code'])
            ->orderByDesc('sold_at')
            ->get([
                'id',
                'number',
                'customer_id',
                'status',
                'sold_at',
                'total',
            ]);

        $byCustomer = [];
        $byPhone = [];

        foreach ($open as $sale) {
            $row = [
                'number' => (string) $sale->number,
                'status' => (string) $sale->status,
                'status_label' => $sale->statusLabel(),
                'sold_at' => $sale->sold_at?->format('d/m/Y H:i'),
                'total' => round((float) $sale->total, 2),
            ];

            $cid = (string) $sale->customer_id;
            if (! isset($byCustomer[$cid])) {
                $byCustomer[$cid] = ['count' => 0, 'sales' => []];
            }
            $byCustomer[$cid]['count']++;
            if (count($byCustomer[$cid]['sales']) < 5) {
                $byCustomer[$cid]['sales'][] = $row;
            }

            $phone = Customer::normalizePhone($sale->customer?->phone);
            if (! $phone) {
                continue;
            }

            $phoneRow = $row + [
                'customer' => trim(($sale->customer?->code ? $sale->customer->code.' — ' : '').($sale->customer?->name ?? '')),
            ];

            if (! isset($byPhone[$phone])) {
                $byPhone[$phone] = ['count' => 0, 'sales' => []];
            }
            $byPhone[$phone]['count']++;
            if (count($byPhone[$phone]['sales']) < 5) {
                $byPhone[$phone]['sales'][] = $phoneRow;
            }
        }

        return [
            'by_customer' => $byCustomer,
            'by_phone' => $byPhone,
        ];
    }

    /**
     * Mapa de riesgo de devolución por cliente y por teléfono (últimos 8 dígitos).
     *
     * @param  Collection<int, Customer>  $customers
     * @return array{by_customer: array<string, array{count:int,last_number:?string,last_at:?string}>, by_phone: array<string, array{count:int,last_number:?string,last_at:?string,names:list<string>}>}
     */
    private function buildCustomerReturnRiskMap($customers): array
    {
        $returned = Sale::query()
            ->where('status', Sale::STATUS_RETURNED)
            ->with(['customer:id,name,phone,code'])
            ->orderByDesc('sold_at')
            ->get(['id', 'number', 'customer_id', 'sold_at']);

        $byCustomer = [];
        $byPhone = [];

        foreach ($returned as $sale) {
            $cid = (string) $sale->customer_id;
            if (! isset($byCustomer[$cid])) {
                $byCustomer[$cid] = [
                    'count' => 0,
                    'last_number' => null,
                    'last_at' => null,
                ];
            }
            $byCustomer[$cid]['count']++;
            if ($byCustomer[$cid]['last_number'] === null) {
                $byCustomer[$cid]['last_number'] = $sale->number;
                $byCustomer[$cid]['last_at'] = $sale->sold_at?->format('d/m/Y');
            }

            $phone = Customer::normalizePhone($sale->customer?->phone);
            if (! $phone) {
                continue;
            }
            if (! isset($byPhone[$phone])) {
                $byPhone[$phone] = [
                    'count' => 0,
                    'last_number' => null,
                    'last_at' => null,
                    'names' => [],
                ];
            }
            $byPhone[$phone]['count']++;
            if ($byPhone[$phone]['last_number'] === null) {
                $byPhone[$phone]['last_number'] = $sale->number;
                $byPhone[$phone]['last_at'] = $sale->sold_at?->format('d/m/Y');
            }
            $label = trim(($sale->customer?->code ? $sale->customer->code.' — ' : '').($sale->customer?->name ?? ''));
            if ($label !== '' && ! in_array($label, $byPhone[$phone]['names'], true)) {
                $byPhone[$phone]['names'][] = $label;
            }
        }

        // Incluir teléfono actual de clientes activos aunque no haya venta cargada (map ya cubre).
        foreach ($customers as $customer) {
            $phone = $customer->normalizedPhone();
            if (! $phone || ! isset($byPhone[$phone])) {
                continue;
            }
            $label = trim($customer->code.' — '.$customer->name);
            if (! in_array($label, $byPhone[$phone]['names'], true)) {
                $byPhone[$phone]['names'][] = $label;
            }
        }

        return [
            'by_customer' => $byCustomer,
            'by_phone' => $byPhone,
        ];
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
            'items.combo',
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

        $sellers = Seller::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'sale_prefix']);

        return view('sales.sales.show', compact('sale', 'products', 'sellers'));
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

        return $this->afterEdit($request, $sale, 'Datos del cliente / entrega actualizados.');
    }

    public function updateInSistrack(Sale $sale): RedirectResponse
    {
        return $this->pushToSistrack($sale, '');
    }

    private function afterEdit(Request $request, Sale $sale, string $message): RedirectResponse
    {
        $sale = $sale->fresh();
        if (! $sale->hasSistrackLabel()) {
            return redirect()->route('sales.sales.show', $sale)->with('success', $message);
        }

        if (! $request->boolean('update_sistrack')) {
            return redirect()->route('sales.sales.show', $sale)
                ->with('success', $message.' Sistrack no se modificó.');
        }

        return $this->pushToSistrack($sale, $message.' ');
    }

    private function pushToSistrack(Sale $sale, string $prefix): RedirectResponse
    {
        $show = redirect()->route('sales.sales.show', $sale);

        try {
            $sync = $this->sistrack->updateInSistrack($sale);
        } catch (Throwable $e) {
            return $show->with('error', $prefix.'Error al actualizar Sistrack: '.$e->getMessage());
        }

        if ($sync['warnings'] !== []) {
            return $show->with('error', $prefix.'Sistrack actualizado con avisos: '.implode(' ', $sync['warnings']));
        }

        return $show->with('success', $prefix.'Sistrack actualizado (misma guía): cliente, dirección, descripción y monto a cobrar '.money($sync['sale']->sistrackCollectAmount()).'.');
    }

    public function updateSeller(UpdateSaleSellerRequest $request, Sale $sale): RedirectResponse
    {
        if ($sale->isVoided()) {
            return back()->withErrors(['sale' => 'No se puede cambiar el vendedor de una venta anulada.']);
        }

        $sale->update([
            'seller_id' => $request->validated('seller_id'),
        ]);

        return redirect()
            ->route('sales.sales.show', $sale)
            ->with('success', 'Vendedor actualizado. El número de venta no cambia.');
    }

    public function addItem(AddSaleItemRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->sales->addItem($sale, $request->validated());
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['sale' => $e->getMessage()]);
        }

        return $this->afterEdit($request, $sale, 'Producto agregado. Totales y stock actualizados.');
    }

    public function updateItem(UpdateSaleItemRequest $request, Sale $sale, SaleItem $item): RedirectResponse
    {
        try {
            $this->sales->updateItem($sale, $item, $request->validated());
        } catch (InvalidArgumentException|RuntimeException|Throwable $e) {
            return back()->withInput()->withErrors(['sale' => $e->getMessage()]);
        }

        return $this->afterEdit($request, $sale, 'Ítem actualizado. Totales y stock recalculados.');
    }

    public function updateDiscounts(UpdateSaleDiscountsRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->sales->updateDiscounts($sale, $request->validated());
        } catch (InvalidArgumentException|RuntimeException|Throwable $e) {
            return back()->withInput()->withErrors(['sale' => $e->getMessage()]);
        }

        return $this->afterEdit($request, $sale, 'Descuentos / total de productos actualizados.');
    }

    public function destroyItem(Request $request, Sale $sale, SaleItem $item): RedirectResponse
    {
        try {
            $this->sales->removeItem($sale, $item);
        } catch (Throwable $e) {
            return back()->withErrors(['sale' => $e->getMessage()]);
        }

        return $this->afterEdit($request, $sale, 'Producto quitado. Stock restaurado y totales recalculados.');
    }

    public function updateShipping(UpdateSaleShippingRequest $request, Sale $sale): RedirectResponse
    {
        try {
            $this->sales->updateShipping($sale, $request->validated());
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['sale' => $e->getMessage()]);
        }

        return $this->afterEdit($request, $sale, 'Envío / notas actualizados. Comisión COD recalculada.');
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

    public function exportReport(Request $request): StreamedResponse|RedirectResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'seller_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
            'shipping' => ['nullable', 'string', 'max:20'],
            'channel' => ['nullable', 'string', 'max:20'],
            'store_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:255'],
            'stuck_in_transit' => ['nullable'],
            'pending_sync' => ['nullable'],
        ]);

        if (! empty($data['from']) && ! empty($data['to']) && $data['to'] < $data['from']) {
            return back()->withErrors([
                'export' => 'La fecha "hasta" no puede ser anterior a "desde".',
            ])->withInput();
        }

        if (isset($data['shipping']) && ! in_array($data['shipping'], ['with', 'without', ''], true)) {
            return back()->withErrors([
                'export' => 'Filtro de envío inválido.',
            ])->withInput();
        }

        try {
            $count = $this->reportExport->filteredQuery($request)->count();
        } catch (Throwable $e) {
            return back()->withErrors([
                'export' => 'No se pudo generar el reporte: '.$e->getMessage(),
            ])->withInput();
        }

        if ($count === 0) {
            return back()->withErrors([
                'export' => 'No hay ventas para exportar con esos filtros.',
            ])->withInput();
        }

        if ($count > 5000) {
            return back()->withErrors([
                'export' => 'Hay demasiadas ventas ('.$count.'). Acota el rango de fechas o filtros e intenta de nuevo.',
            ])->withInput();
        }

        return $this->reportExport->downloadFromRequest($request);
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

    public function sendToSistrack(Request $request, Sale $sale): RedirectResponse|JsonResponse
    {
        try {
            $updated = $this->sistrack->sendOne($sale);
        } catch (Throwable $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'sale_id' => $sale->id,
                    'number' => $sale->number,
                    'message' => $e->getMessage(),
                    'sistrack_status' => $sale->fresh()?->sistrack_status,
                ], 422);
            }

            return back()->withErrors(['sistrack' => $e->getMessage()]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'sale_id' => $updated->id,
                'number' => $updated->number,
                'sistrack_status' => $updated->sistrack_status,
                'sistrack_external_id' => $updated->sistrack_external_id,
                'message' => 'Venta enviada a Sistrack correctamente.',
            ]);
        }

        return back()->with('success', 'Venta enviada a Sistrack correctamente.');
    }

    public function resendToSistrack(Request $request, Sale $sale): RedirectResponse|JsonResponse
    {
        try {
            $updated = $this->sistrack->resendOne($sale);
        } catch (Throwable $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'sale_id' => $sale->id,
                    'number' => $sale->number,
                    'message' => $e->getMessage(),
                    'sistrack_status' => $sale->fresh()?->sistrack_status,
                ], 422);
            }

            return back()->withErrors(['sistrack' => $e->getMessage()]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'sale_id' => $updated->id,
                'number' => $updated->number,
                'sistrack_status' => $updated->sistrack_status,
                'sistrack_external_id' => $updated->sistrack_external_id,
                'message' => 'Venta reenviada a Sistrack. Se creó una etiqueta nueva.',
            ]);
        }

        return back()->with('success', 'Venta reenviada a Sistrack. Se creó una etiqueta nueva.');
    }

    public function sendManyToSistrack(Request $request): RedirectResponse
    {
        $request->validate([
            'sale_ids' => ['required', 'array', 'min:1'],
            'sale_ids.*' => ['integer', 'exists:sales,id'],
        ]);

        $sales = Sale::query()
            ->with(['customer', 'seller', 'items.product', 'shippingCarrier'])
            ->whereIn('id', $request->input('sale_ids', []))
            ->get();

        $result = $this->sistrack->sendMany($sales);
        $sentCount = count($result['sent']);
        $failedCount = count($result['failed']);
        $skippedCount = count($result['skipped']);

        if ($failedCount > 0) {
            $details = collect($result['failed'])
                ->map(fn (array $row) => $row['sale']->number.': '.$row['error'])
                ->take(5)
                ->implode(' | ');

            return back()->withErrors([
                'sistrack' => "Enviadas: {$sentCount}. Fallidas: {$failedCount}. Omitidas: {$skippedCount}. ".$details,
            ])->with('success', $sentCount > 0
                ? "Se enviaron {$sentCount} venta(s) a Sistrack. Las fallidas quedan para reintentar."
                : null);
        }

        return back()->with(
            'success',
            "Sistrack: {$sentCount} enviada(s), {$skippedCount} omitida(s)."
        );
    }

    public function syncSistrackStatus(Request $request, Sale $sale): RedirectResponse|JsonResponse
    {
        try {
            $result = $this->sistrack->syncOneStatus($sale);
            $updated = $result['sale'];
        } catch (Throwable $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'sale_id' => $sale->id,
                    'number' => $sale->number,
                    'message' => $e->getMessage(),
                    'status' => $sale->fresh()?->status,
                    'status_label' => $sale->fresh()?->statusLabel(),
                ], 422);
            }

            return back()->withErrors(['sistrack' => $e->getMessage()]);
        }

        $message = $result['changed']
            ? 'Estado actualizado a '.$updated->statusLabel().'.'
            : 'Sin cambios (Sistrack: '.($updated->sistrack_shipping_status ?: '—').').';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'changed' => $result['changed'],
                'sale_id' => $updated->id,
                'number' => $updated->number,
                'status' => $updated->status,
                'status_label' => $updated->statusLabel(),
                'status_badge' => $updated->statusBadgeClass(),
                'sistrack_shipping_status' => $updated->sistrack_shipping_status,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    public function syncManySistrackStatus(Request $request): RedirectResponse
    {
        $request->validate([
            'sale_ids' => ['nullable', 'array'],
            'sale_ids.*' => ['integer', 'exists:sales,id'],
        ]);

        $ids = $request->input('sale_ids', []);
        $sales = filled($ids)
            ? Sale::query()->with('shippingCarrier')->whereIn('id', $ids)->get()
            : Sale::query()->with('shippingCarrier')->pendingSistrackStatusSync()->orderBy('sold_at')->limit(500)->get();

        if ($sales->isEmpty()) {
            return back()->with('success', 'No hay ventas pendientes de sincronizar.');
        }

        $result = $this->sistrack->syncStatuses($sales);
        $updated = count($result['updated']);
        $failed = count($result['failed']);
        $unchanged = count($result['unchanged']);

        if ($failed > 0) {
            $details = collect($result['failed'])
                ->map(fn (array $row) => $row['sale']->number.': '.$row['error'])
                ->take(5)
                ->implode(' | ');

            return back()->withErrors([
                'sistrack' => "Actualizadas: {$updated}. Sin cambio: {$unchanged}. Fallidas: {$failed}. ".$details,
            ]);
        }

        return back()->with(
            'success',
            "Estados Sistrack: {$updated} actualizada(s), {$unchanged} sin cambio."
        );
    }

    public function pendingSistrackStatusSync(): JsonResponse
    {
        $sales = Sale::query()
            ->pendingSistrackStatusSync()
            ->orderBy('sold_at')
            ->limit(500)
            ->get(['id', 'number']);

        return response()->json([
            'count' => $sales->count(),
            'sales' => $sales->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'number' => $sale->number,
                'sync_url' => route('sales.sales.sync-sistrack-status.one', $sale),
            ])->values(),
        ]);
    }

    public function markDelivered(Request $request, Sale $sale): RedirectResponse|JsonResponse
    {
        try {
            $updated = $this->sales->markDelivered($sale);
        } catch (Throwable $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'sale_id' => $sale->id,
                    'number' => $sale->number,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['status' => $e->getMessage()]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'sale_id' => $updated->id,
                'number' => $updated->number,
                'status' => $updated->status,
                'status_label' => $updated->statusLabel(),
                'message' => 'Venta marcada como entregada.',
            ]);
        }

        return back()->with('success', 'Venta marcada como entregada.');
    }

    public function markReturned(Request $request, Sale $sale): RedirectResponse|JsonResponse
    {
        try {
            $updated = $this->sales->markReturned($sale);
        } catch (Throwable $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'sale_id' => $sale->id,
                    'number' => $sale->number,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['status' => $e->getMessage()]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'sale_id' => $updated->id,
                'number' => $updated->number,
                'status' => $updated->status,
                'status_label' => $updated->statusLabel(),
                'message' => 'Venta marcada como devolución.',
            ]);
        }

        return back()->with('success', 'Venta marcada como devolución (stock reingresado).');
    }

    public function markManyDelivered(Request $request): RedirectResponse
    {
        $request->validate([
            'sale_ids' => ['required', 'array', 'min:1'],
            'sale_ids.*' => ['integer', 'exists:sales,id'],
        ]);

        $sales = Sale::query()->whereIn('id', $request->input('sale_ids', []))->get();
        $ok = 0;
        $fail = 0;

        foreach ($sales as $sale) {
            try {
                $this->sales->markDelivered($sale);
                $ok++;
            } catch (Throwable) {
                $fail++;
            }
        }

        return back()->with(
            'success',
            "Marcadas entregadas: {$ok}".($fail ? ". Fallidas: {$fail}." : '.')
        );
    }

    public function markManyReturned(Request $request): RedirectResponse
    {
        $request->validate([
            'sale_ids' => ['required', 'array', 'min:1'],
            'sale_ids.*' => ['integer', 'exists:sales,id'],
        ]);

        $sales = Sale::query()->whereIn('id', $request->input('sale_ids', []))->get();
        $ok = 0;
        $fail = 0;

        foreach ($sales as $sale) {
            try {
                $this->sales->markReturned($sale);
                $ok++;
            } catch (Throwable) {
                $fail++;
            }
        }

        return back()->with(
            'success',
            "Marcadas devolución: {$ok}".($fail ? ". Fallidas: {$fail}." : '.')
        );
    }

    public function voidMany(Request $request): RedirectResponse
    {
        $request->validate([
            'sale_ids' => ['required', 'array', 'min:1'],
            'sale_ids.*' => ['integer', 'exists:sales,id'],
        ]);

        $sales = Sale::query()->whereIn('id', $request->input('sale_ids', []))->get();
        $ok = 0;
        $fail = 0;
        $errors = [];

        foreach ($sales as $sale) {
            try {
                $this->sales->void($sale);
                $ok++;
            } catch (Throwable $e) {
                $fail++;
                if (count($errors) < 5) {
                    $errors[] = $sale->number.': '.$e->getMessage();
                }
            }
        }

        if ($fail > 0) {
            return back()->withErrors([
                'status' => "Anuladas: {$ok}. Fallidas: {$fail}. ".implode(' | ', $errors),
            ]);
        }

        return back()->with('success', "Se anularon {$ok} venta(s) y se restauró stock.");
    }
}
