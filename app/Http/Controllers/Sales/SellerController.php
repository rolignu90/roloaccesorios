<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSellerRequest;
use App\Http\Requests\Sales\UpdateSellerRequest;
use App\Models\Customer;
use App\Models\Seller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SellerController extends Controller
{
    public function index(Request $request): View
    {
        $sellers = Seller::query()
            ->with('customer:id,code,name')
            ->withCount('sales')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('sales.sellers.index', compact('sellers'));
    }

    public function create(): View
    {
        return view('sales.sellers.create', [
            'customers' => $this->customerOptions(),
            'nextCode' => Seller::nextCode(),
        ]);
    }

    public function store(StoreSellerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] = Seller::nextCode();

        Seller::query()->create($data);

        return redirect()
            ->route('sales.sellers.index')
            ->with('success', 'Vendedor creado correctamente.');
    }

    public function show(Seller $seller): View
    {
        $seller->load([
            'customer',
            'sales' => fn ($q) => $q->with('customer')->latest('sold_at')->limit(20),
        ]);

        return view('sales.sellers.show', compact('seller'));
    }

    public function edit(Seller $seller): View
    {
        return view('sales.sellers.edit', [
            'seller' => $seller->load('customer'),
            'customers' => $this->customerOptions($seller->id),
        ]);
    }

    public function update(UpdateSellerRequest $request, Seller $seller): RedirectResponse
    {
        $seller->update($request->validated());

        return redirect()
            ->route('sales.sellers.index')
            ->with('success', 'Vendedor actualizado correctamente.');
    }

    public function destroy(Seller $seller): RedirectResponse
    {
        if ($seller->sales()->exists()) {
            $seller->update(['is_active' => false]);

            return redirect()
                ->route('sales.sellers.index')
                ->with('success', 'El vendedor tiene ventas; se desactivó para conservar el historial.');
        }

        $seller->delete();

        return redirect()
            ->route('sales.sellers.index')
            ->with('success', 'Vendedor eliminado correctamente.');
    }

    /**
     * @return Collection<int, Customer>
     */
    private function customerOptions(?int $ignoreSellerId = null): Collection
    {
        $linkedIds = Seller::query()
            ->when($ignoreSellerId, fn ($q) => $q->where('id', '!=', $ignoreSellerId))
            ->whereNotNull('customer_id')
            ->pluck('customer_id');

        return Customer::query()
            ->where('is_active', true)
            ->where(function ($q) use ($linkedIds, $ignoreSellerId) {
                $q->whereNotIn('id', $linkedIds);
                if ($ignoreSellerId) {
                    $current = Seller::query()->where('id', $ignoreSellerId)->value('customer_id');
                    if ($current) {
                        $q->orWhere('id', $current);
                    }
                }
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }
}
