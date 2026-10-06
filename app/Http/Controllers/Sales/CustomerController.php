<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCustomerRequest;
use App\Http\Requests\Sales\SyncCustomerProductPricesRequest;
use App\Http\Requests\Sales\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerProductPriceTier;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $onlyReturns = $request->boolean('returns');

        $customers = Customer::query()
            ->withCount([
                'sales as returns_count' => fn ($q) => $q->where('status', Sale::STATUS_RETURNED),
            ])
            ->withMax([
                'sales as last_return_at' => fn ($q) => $q->where('status', Sale::STATUS_RETURNED),
            ], 'sold_at')
            ->when($onlyReturns, fn ($q) => $q->withReturns())
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('document_number', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $returnsCustomersCount = Customer::query()->withReturns()->count();

        return view('sales.customers.index', compact('customers', 'onlyReturns', 'returnsCustomersCount'));
    }

    public function create(): View
    {
        return view('sales.customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['code']);

        Customer::createWithNextCode($data);

        return redirect()
            ->route('sales.customers.index')
            ->with('success', 'Cliente creado correctamente.');
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'sales' => fn ($q) => $q->latest('sold_at')->limit(20),
            'returnedSales' => fn ($q) => $q->latest('sold_at')->limit(20),
            'productPriceTiers.product',
        ]);

        $priceGroups = $customer->productPriceTiers
            ->groupBy('product_id')
            ->map(function ($tiers) {
                return [
                    'product' => $tiers->first()->product,
                    'tiers' => $tiers->sortBy('min_quantity')->values(),
                ];
            })
            ->values();

        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'sale_price_without_vat', 'wholesale_price_without_vat']);

        return view('sales.customers.show', compact('customer', 'priceGroups', 'products'));
    }

    public function syncProductPrices(SyncCustomerProductPricesRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($customer, $data) {
            CustomerProductPriceTier::query()
                ->where('customer_id', $customer->id)
                ->where('product_id', $data['product_id'])
                ->delete();

            foreach ($data['tiers'] as $tier) {
                CustomerProductPriceTier::query()->create([
                    'customer_id' => $customer->id,
                    'product_id' => $data['product_id'],
                    'min_quantity' => $tier['min_quantity'],
                    'unit_price_without_vat' => price_without_vat($tier['unit_price_with_vat']),
                ]);
            }
        });

        return redirect()
            ->route('sales.customers.show', $customer)
            ->with('success', 'Precios del producto actualizados para este cliente.');
    }

    public function destroyProductPrices(Customer $customer, Product $product): RedirectResponse
    {
        CustomerProductPriceTier::query()
            ->where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->delete();

        return redirect()
            ->route('sales.customers.show', $customer)
            ->with('success', 'Precios especiales eliminados para ese producto.');
    }

    public function edit(Customer $customer): View
    {
        $customer->load(['productPriceTiers.product']);

        $priceGroups = $customer->productPriceTiers
            ->groupBy('product_id')
            ->map(function ($tiers) {
                return [
                    'product' => $tiers->first()->product,
                    'tiers' => $tiers->sortBy('min_quantity')->values(),
                ];
            })
            ->values();

        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'sale_price_without_vat', 'wholesale_price_without_vat']);

        return view('sales.customers.edit', compact('customer', 'priceGroups', 'products'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()
            ->route('sales.customers.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->sales()->exists()) {
            return back()->withErrors(['customer' => 'No se puede eliminar: el cliente tiene ventas.']);
        }

        $customer->delete();

        return redirect()
            ->route('sales.customers.index')
            ->with('success', 'Cliente eliminado correctamente.');
    }
}
