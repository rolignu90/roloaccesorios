<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreService;
use App\Support\CurrentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CashSessionController extends Controller
{
    public function __construct(private StoreService $store) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $stores = $user->accessibleStores();
        $open = CashSession::query()->with('cashier:id,name')->open()->whereIn('store_id', $stores->pluck('id'))->get()->keyBy('store_id');

        $panels = $stores->map(fn (Store $store) => [
            'store' => $store,
            'session' => $open->get($store->id),
            'summary' => $open->has($store->id) ? $this->store->summary($open->get($store->id)) : null,
        ]);

        $sessions = CashSession::query()
            ->with('store:id,name')
            ->unless($user->hasPermission('cash.view_all'), fn ($q) => $q->whereIn('store_id', $stores->pluck('id')))
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', $request->integer('store_id')))
            ->latest('opened_at')
            ->paginate(20)
            ->withQueryString();

        return view('store.cash.index', [
            'panels' => $panels,
            'deviceStore' => CurrentStore::resolve($request),
            'allStores' => $stores,
            'sellers' => $this->activeSellers(),
            'sessions' => $sessions,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')->where('is_active', true)],
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'cashier_id' => ['nullable', 'integer', Rule::exists('sellers', 'id')->where('is_active', true)],
            'opened_by' => ['nullable', 'string', 'max:100'],
            'opening_notes' => ['nullable', 'string', 'max:1000'],
            'redirect_to_pos' => ['sometimes', 'boolean'],
        ]);

        $store = Store::query()->findOrFail($data['store_id']);
        abort_unless($request->user()->canAccessStore($store), 403, 'No tienes acceso a esa tienda.');
        $data['opened_by_user_id'] = $request->user()->id;
        $data['opened_by'] = $request->user()->name;

        try {
            $session = $this->store->openSession($store, $data);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = "Caja {$session->number} de {$session->store->name} abierta con ".money($session->opening_amount).'.';

        return $request->boolean('redirect_to_pos')
            ? redirect()->route('store.pos')->with('success', $message)
            : redirect()->route('store.cash.show', $session)->with('success', $message);
    }

    public function show(Request $request, CashSession $cashSession): View
    {
        $this->authorizeSession($request, $cashSession);

        $cashSession->load([
            'store',
            'cashier:id,name',
            'movements' => fn ($q) => $q->with('user:id,name')->latest('occurred_at'),
            'sales' => fn ($q) => $q->with(['customer:id,name', 'seller:id,name'])->latest('sold_at'),
        ]);

        return view('store.cash.show', [
            'sellers' => $this->activeSellers(),
            'team' => $cashSession->isOpen() ? $this->storeTeam($cashSession->store) : collect(),
            'session' => $cashSession,
            'summary' => $this->store->summary($cashSession),
        ]);
    }

    public function changeCashier(Request $request, CashSession $cashSession): RedirectResponse
    {
        $this->authorizeSession($request, $cashSession);

        $data = $request->validate([
            'cashier_id' => ['nullable', 'integer', Rule::exists('sellers', 'id')->where('is_active', true)],
        ]);

        try {
            $session = $this->store->changeCashier(
                $cashSession,
                ! empty($data['cashier_id']) ? Seller::query()->find($data['cashier_id']) : null,
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $session->cashier
            ? "Cajero cambiado a {$session->cashier->name}. Las nuevas ventas saldrán a su nombre."
            : 'La caja quedó sin cajero asignado.');
    }

    public function storeMovement(Request $request, CashSession $cashSession): RedirectResponse
    {
        $this->authorizeSession($request, $cashSession);

        $data = $request->validate([
            'type' => ['required', Rule::in([CashMovement::TYPE_IN, CashMovement::TYPE_OUT])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->store->addMovement($cashSession, $data + ['user_id' => $request->user()->id]);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Movimiento de caja registrado.');
    }

    public function close(Request $request, CashSession $cashSession): RedirectResponse
    {
        $this->authorizeSession($request, $cashSession);

        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'closed_by_member_id' => ['required', 'integer'],
            'closing_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'closed_by_member_id.required' => 'Selecciona quién cierra la caja.',
        ]);

        $member = $this->storeTeam($cashSession->store)->firstWhere('id', (int) $data['closed_by_member_id']);
        if (! $member) {
            return back()->withInput()->with('error', 'La persona seleccionada no pertenece al equipo de esta tienda.');
        }

        try {
            $session = $this->store->closeSession($cashSession, [
                'counted_cash' => $data['counted_cash'],
                'closing_notes' => $data['closing_notes'] ?? null,
                'closed_by' => $member->name,
                'closed_by_user_id' => $request->user()->id,
            ]);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $diff = (float) $session->difference;
        $label = abs($diff) < 0.01 ? 'cuadrada' : ($diff > 0 ? 'sobrante '.money($diff) : 'faltante '.money(abs($diff)));

        return redirect()
            ->route('store.cash.show', $session)
            ->with('success', "Caja cerrada ({$label}).");
    }

    public function report(Request $request, CashSession $cashSession): View
    {
        $this->authorizeSession($request, $cashSession);

        $cashSession->load(['store', 'cashier:id,name']);

        return view('store.cash.report', [
            'session' => $cashSession,
            'summary' => $this->store->summary($cashSession),
            'store' => $cashSession->store,
        ]);
    }

    private function authorizeSession(Request $request, CashSession $cashSession): void
    {
        abort_unless(
            $request->user()->hasPermission('cash.view_all') || $request->user()->canAccessStore($cashSession->store),
            403,
            'No tienes acceso a la caja de esa tienda.'
        );
    }

    private function storeTeam(?Store $store): Collection
    {
        return User::query()
            ->with(['role', 'stores:id'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role_id', 'seller_id', 'is_active'])
            ->filter(fn (User $user) => $store && (
                $user->hasPermission('cash.view_all') || $user->stores->contains('id', $store->id)
            ))
            ->values();
    }

    private function activeSellers()
    {
        return Seller::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sale_prefix']);
    }
}
