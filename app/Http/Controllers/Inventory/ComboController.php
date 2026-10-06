<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreComboRequest;
use App\Http\Requests\Inventory\UpdateComboRequest;
use App\Models\Combo;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ComboController extends Controller
{
    public function index(Request $request): View
    {
        $combos = Combo::query()
            ->with(['items.product'])
            ->withCount('items')
            ->when(! $request->boolean('show_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $combos->getCollection()->transform(function (Combo $combo) {
            $combo->setAttribute('available_stock', $combo->availableComboStock());

            return $combo;
        });

        return view('inventory.combos.index', compact('combos'));
    }

    public function create(): View
    {
        return view('inventory.combos.create', [
            'products' => $this->activeProducts(),
        ]);
    }

    public function store(StoreComboRequest $request): RedirectResponse
    {
        $combo = DB::transaction(function () use ($request) {
            $combo = Combo::query()->create($request->comboAttributes());
            $combo->syncItems($request->validated('items'));

            return $combo;
        });

        return redirect()
            ->route('inventory.combos.show', $combo)
            ->with('success', 'Combo creado correctamente.');
    }

    public function show(Combo $combo): View
    {
        $combo->load(['items.product' => fn ($q) => $q->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')]);
        $combo->setAttribute('available_stock', $combo->availableComboStock());

        return view('inventory.combos.show', compact('combo'));
    }

    public function edit(Combo $combo): View
    {
        $combo->load('items');

        return view('inventory.combos.edit', [
            'combo' => $combo,
            'products' => $this->activeProducts($combo),
        ]);
    }

    public function update(UpdateComboRequest $request, Combo $combo): RedirectResponse
    {
        DB::transaction(function () use ($request, $combo) {
            $combo->update($request->comboAttributes());
            $combo->syncItems($request->validated('items'));
        });

        return redirect()
            ->route('inventory.combos.show', $combo)
            ->with('success', 'Combo actualizado correctamente.');
    }

    public function destroy(Combo $combo): RedirectResponse
    {
        $combo->update(['is_active' => false]);

        return redirect()
            ->route('inventory.combos.index')
            ->with('success', 'Combo desactivado. El historial de ventas se conserva.');
    }

    public function duplicate(Combo $combo): RedirectResponse
    {
        $copy = $combo->duplicate();

        return redirect()
            ->route('inventory.combos.edit', $copy)
            ->with('success', "Combo duplicado como {$copy->code}. Revisa y guarda si necesitas ajustar.");
    }

    private function activeProducts(?Combo $combo = null)
    {
        $ids = $combo?->items->pluck('product_id') ?? collect();

        return Product::query()
            ->where(function ($query) use ($ids) {
                $query->where('is_active', true)
                    ->orWhereIn('id', $ids);
            })
            ->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')
            ->orderBy('name')
            ->get();
    }
}
