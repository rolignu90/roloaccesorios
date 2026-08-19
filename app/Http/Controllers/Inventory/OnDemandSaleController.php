<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLotAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OnDemandSaleController extends Controller
{
    public function index(Request $request): View
    {
        $base = SaleLotAllocation::query()
            ->whereNull('sale_lot_allocations.inventory_lot_id')
            ->whereHas('saleItem.sale', function ($query) use ($request) {
                $query->onDemandVisible()
                    ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                    ->when($request->filled('from'), fn ($q) => $q->whereDate('sold_at', '>=', $request->date('from')))
                    ->when($request->filled('to'), fn ($q) => $q->whereDate('sold_at', '<=', $request->date('to')));
            })
            ->when($request->filled('product_id'), function ($query) use ($request) {
                $query->whereHas('saleItem', fn ($q) => $q->where('product_id', $request->integer('product_id')));
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->whereHas('saleItem.product', function ($product) use ($term) {
                        $product->where('code', 'like', "%{$term}%")
                            ->orWhere('name', 'like', "%{$term}%");
                    })->orWhereHas('saleItem.sale', function ($sale) use ($term) {
                        $sale->where('number', 'like', "%{$term}%");
                    });
                });
            });

        $summary = (clone $base)
            ->join('sale_items', 'sale_items.id', '=', 'sale_lot_allocations.sale_item_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->select([
                'products.id as product_id',
                'products.code',
                'products.name',
                DB::raw('SUM(sale_lot_allocations.quantity) as qty_pending'),
                DB::raw('SUM(sale_lot_allocations.cogs_amount) as cogs_estimated'),
            ])
            ->groupBy('products.id', 'products.code', 'products.name')
            ->orderByDesc('qty_pending')
            ->get();

        $lines = (clone $base)
            ->with([
                'saleItem.product',
                'saleItem.sale.customer',
            ])
            ->latest('sale_lot_allocations.id')
            ->paginate(30)
            ->withQueryString();

        $products = Product::query()
            ->where('on_demand', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('inventory.on-demand.index', [
            'summary' => $summary,
            'lines' => $lines,
            'products' => $products,
            'totalQty' => (int) $summary->sum('qty_pending'),
            'totalCogs' => round((float) $summary->sum('cogs_estimated'), 2),
        ]);
    }
}
