<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryMovementController extends Controller
{
    public function index(Request $request): View
    {
        $movements = InventoryMovement::query()
            ->with(['product', 'inventoryLot'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('occurred_at', '<=', $request->date('to')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('notes', 'like', "%{$term}%")
                        ->orWhereHas('product', function ($product) use ($term) {
                            $product->where('code', 'like', "%{$term}%")
                                ->orWhere('name', 'like', "%{$term}%");
                        })
                        ->orWhereHas('inventoryLot', fn ($lot) => $lot->where('lot_number', 'like', "%{$term}%"));
                });
            })
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $products = Product::query()->orderBy('name')->get(['id', 'code', 'name']);

        return view('inventory.movements.index', [
            'movements' => $movements,
            'products' => $products,
            'types' => InventoryMovement::TYPES,
        ]);
    }
}
