<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreSupplierRequest;
use App\Http\Requests\Inventory\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        $suppliers = Supplier::query()
            ->orderBy('name')
            ->paginate(15);

        return view('inventory.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        return view('inventory.suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::query()->create($request->validated());

        return redirect()
            ->route('inventory.suppliers.index')
            ->with('success', 'Proveedor creado correctamente.');
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load(['products' => fn ($q) => $q->orderBy('name')]);

        return view('inventory.suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier): View
    {
        return view('inventory.suppliers.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()
            ->route('inventory.suppliers.index')
            ->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return redirect()
            ->route('inventory.suppliers.index')
            ->with('success', 'Proveedor eliminado correctamente.');
    }
}
