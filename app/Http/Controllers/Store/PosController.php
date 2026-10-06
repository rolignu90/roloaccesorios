<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\PosCheckoutRequest;
use App\Models\CashSession;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreService;
use App\Support\CurrentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PosController extends Controller
{
    public function __construct(private StoreService $store) {}

    public function index(Request $request): View|RedirectResponse
    {
        $currentStore = CurrentStore::resolve($request);
        if (! $currentStore) {
            return redirect()->route('store.select');
        }

        $session = CashSession::currentFor($currentStore)?->load('cashier:id,name');

        $products = Product::query()
            ->where('is_active', true)
            ->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')
            ->orderByDesc('is_favorite')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'code' => (string) $product->code,
                'name' => $product->name,
                'price' => $product->effectiveSalePriceWithVat(),
                'stock' => (int) ($product->stock_on_hand ?? 0),
                'on_demand' => (bool) $product->on_demand,
                'favorite' => (bool) $product->is_favorite,
            ])
            ->values();

        $combos = Combo::query()
            ->where('is_active', true)
            ->with(['items.product' => fn ($q) => $q->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')])
            ->orderBy('name')
            ->get()
            ->map(fn (Combo $combo) => [
                'id' => $combo->id,
                'code' => (string) $combo->code,
                'name' => $combo->name,
                'price' => $combo->salePriceWithVat(),
                'stock' => $combo->availableComboStock(),
            ])
            ->values();

        $user = $request->user();
        $storeSeller = $this->store->sellerFor($currentStore);
        $walkIn = $this->store->walkInCustomer();

        return view('store.pos', [
            'currentStore' => $currentStore,
            'hasManyStores' => $user->accessibleStores()->count() > 1,
            'defaultSellerId' => $user->seller_id ?? $session?->cashier_id ?? $storeSeller->id,
            'canDiscount' => $user->hasPermission('pos.discount'),
            'canOverridePrice' => $user->hasPermission('pos.price_override'),
            'canSwitchUser' => User::query()->active()->whereNotNull('pos_pin')->whereKeyNot($user->id)->exists(),
            'session' => $session,
            'summary' => $session ? $this->store->summary($session) : null,
            'products' => $products,
            'combos' => $combos,
            'sellers' => Seller::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'phone']),
            'storeSellerId' => $storeSeller->id,
            'walkInCustomerId' => $walkIn->id,
            'paymentMethods' => collect(config('sales.store.payment_methods'))
                ->mapWithKeys(fn ($key) => [$key => config('sales.payment_methods.'.$key, $key)])
                ->all(),
        ]);
    }

    public function selectStore(Request $request): View
    {
        return view('store.select', [
            'stores' => $request->user()->accessibleStores()->load('seller:id,name,sale_prefix'),
            'current' => CurrentStore::resolve($request),
            'redirect' => $request->query('redirect') === 'cash' ? 'cash' : 'pos',
        ]);
    }

    public function rememberStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')->where('is_active', true)],
            'redirect' => ['nullable', 'in:pos,cash'],
        ]);

        $store = Store::query()->findOrFail($data['store_id']);
        abort_unless($request->user()->canAccessStore($store), 403, 'No tienes acceso a esa tienda.');
        CurrentStore::remember($store);

        return redirect()
            ->route(($data['redirect'] ?? 'pos') === 'cash' ? 'store.cash.index' : 'store.pos')
            ->with('success', "Este equipo ahora trabaja como {$store->name}.");
    }

    public function checkout(PosCheckoutRequest $request): RedirectResponse
    {
        $currentStore = CurrentStore::resolve($request);
        if (! $currentStore) {
            return redirect()->route('store.select')->with('error', 'Elige la tienda de este equipo antes de cobrar.');
        }

        try {
            $sale = $this->store->checkout($currentStore, $request->checkoutData());
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('store.sales.ticket', ['sale' => $sale, 'print' => 1]);
    }

    /**
     * Quick cashier change on a shared POS device using the personal PIN.
     */
    public function switchUser(Request $request): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'digits_between:4,6']]);
        $currentStore = CurrentStore::resolve($request);

        $target = User::query()
            ->active()
            ->whereNotNull('pos_pin')
            ->with('role')
            ->get()
            ->first(fn (User $user) => $user->checkPosPin($data['pin']));

        if (! $target || ! $target->hasPermission('pos.sell') || ($currentStore && ! $target->canAccessStore($currentStore))) {
            return back()->with('error', 'PIN incorrecto o sin acceso a esta tienda.');
        }

        if ($target->must_change_password) {
            return back()->with('error', "{$target->name} debe iniciar sesión con contraseña y cambiarla primero.");
        }

        Auth::login($target);
        $request->session()->regenerate();
        $target->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('store.pos')->with('success', "Cajero actual: {$target->name}.");
    }

    /**
     * Prints a small slip; the POS printer driver fires the cash drawer after each print job.
     */
    public function drawer(Request $request): View|RedirectResponse
    {
        $currentStore = CurrentStore::resolve($request);
        if (! $currentStore) {
            return redirect()->route('store.select');
        }

        $session = CashSession::currentFor($currentStore);

        Log::info('pos.drawer_open', [
            'user_id' => $request->user()->id,
            'store_id' => $currentStore->id,
            'cash_session_id' => $session?->id,
            'ip' => $request->ip(),
        ]);

        return view('store.drawer', [
            'store' => $currentStore,
            'session' => $session,
            'user' => $request->user(),
        ]);
    }

    public function ticket(Sale $sale): View
    {
        $sale->load(['customer', 'seller', 'items.product', 'items.combo', 'cashSession', 'store']);

        return view('store.ticket', [
            'sale' => $sale,
            'store' => $sale->store,
        ]);
    }
}
