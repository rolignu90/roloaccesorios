<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FreeShippingController extends Controller
{
    public function index(Request $request): View
    {
        $freeShippingProducts = Product::query()
            ->where('free_shipping', true)
            ->orderBy('name')
            ->get();

        $availableProducts = Product::query()
            ->where('is_active', true)
            ->where('free_shipping', false)
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->get();

        return view('inventory.free-shipping.index', compact('freeShippingProducts', 'availableProducts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ], [
            'product_ids.required' => 'Selecciona al menos un producto.',
        ]);

        Product::query()
            ->whereIn('id', $data['product_ids'])
            ->update(['free_shipping' => true]);

        return redirect()
            ->route('inventory.free-shipping.index')
            ->with('success', 'Productos agregados a envío gratis.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->update(['free_shipping' => false]);

        return redirect()
            ->route('inventory.free-shipping.index')
            ->with('success', "{$product->name} ya no tiene envío gratis.");
    }
}
