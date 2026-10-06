<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveStoreRequest;
use App\Models\CashSession;
use App\Models\Seller;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class StoreController extends Controller
{
    public function __construct(private StoreService $store) {}

    public function index(): View
    {
        $stores = Store::query()
            ->with('seller:id,name,sale_prefix')
            ->withCount(['sales as store_sales_count' => fn ($q) => $q->where('status', '!=', 'voided')])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $openStoreIds = CashSession::query()->open()->pluck('store_id')->all();

        return view('settings.stores.index', compact('stores', 'openStoreIds'));
    }

    public function create(): View
    {
        return view('settings.stores.create', [
            'store' => new Store(['is_active' => true, 'ticket_footer' => '¡Gracias por su compra!']),
            'sellers' => $this->sellers(),
        ]);
    }

    public function store(SaveStoreRequest $request): RedirectResponse
    {
        try {
            $store = $this->store->saveStore(new Store, $request->validated());
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('settings.stores.index')->with('success', "Tienda {$store->name} creada.");
    }

    public function edit(Store $store): View
    {
        return view('settings.stores.edit', [
            'store' => $store,
            'sellers' => $this->sellers(),
        ]);
    }

    public function update(SaveStoreRequest $request, Store $store): RedirectResponse
    {
        $data = $request->validated();

        if (! $data['is_active'] && CashSession::currentFor($store)) {
            return back()->withInput()->with('error', 'Cierra la caja de esta tienda antes de desactivarla.');
        }

        try {
            $this->store->saveStore($store, $data);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('settings.stores.index')->with('success', "Tienda {$store->name} actualizada.");
    }

    private function sellers()
    {
        return Seller::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sale_prefix']);
    }
}
