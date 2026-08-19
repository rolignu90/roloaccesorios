<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockReceiptRequest;
use App\Http\Requests\Inventory\UpdateStockReceiptRequest;
use App\Models\InventoryLot;
use App\Models\Product;
use App\Services\FifoInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class StockReceiptController extends Controller
{
    public function __construct(private FifoInventoryService $fifo)
    {
    }

    public function index(Request $request): View
    {
        $lots = InventoryLot::query()
            ->with(['product', 'supplier'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('lot_number', 'like', "%{$term}%")
                        ->orWhere('invoice_reference', 'like', "%{$term}%")
                        ->orWhereHas('product', function ($product) use ($term) {
                            $product->where('code', 'like', "%{$term}%")
                                ->orWhere('name', 'like', "%{$term}%");
                        });
                });
            })
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->latest('received_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $products = Product::query()->orderBy('name')->get(['id', 'code', 'name']);

        return view('inventory.stock.index', compact('lots', 'products'));
    }

    public function create(): View
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with(['productSuppliers.supplier'])
            ->orderBy('name')
            ->get();

        $pendingOnDemand = $products->mapWithKeys(function (Product $product) {
            return [(string) $product->id => $this->fifo->pendingOnDemandQuantity($product->id)];
        })->all();

        return view('inventory.stock.create', compact('products', 'pendingOnDemand'));
    }

    public function store(StoreStockReceiptRequest $request): RedirectResponse
    {
        $lot = $this->fifo->receiveStock($request->validated());
        $covered = (int) ($lot->on_demand_covered ?? 0);
        $msg = "Entrada de stock registrada (lote {$lot->lot_number}).";
        if ($covered > 0) {
            $msg .= " Cubrió {$covered} unidad(es) on demand.";
        }

        return redirect()
            ->route('inventory.stock.index')
            ->with('success', $msg);
    }

    public function edit(InventoryLot $lot): View
    {
        $lot->load(['product.productSuppliers.supplier', 'supplier']);

        return view('inventory.stock.edit', [
            'lot' => $lot,
            'unused' => (int) $lot->quantity_remaining === (int) $lot->quantity_received
                && (int) $lot->quantity_remaining > 0,
            'soldQty' => (int) $lot->quantity_received - (int) $lot->quantity_remaining,
        ]);
    }

    public function update(UpdateStockReceiptRequest $request, InventoryLot $lot): RedirectResponse
    {
        try {
            $this->fifo->correctLot($lot, $request->validated());
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['lot' => $e->getMessage()]);
        }

        return redirect()
            ->route('inventory.stock.index')
            ->with('success', "Entrada {$lot->lot_number} rectificada.");
    }

    public function destroy(Request $request, InventoryLot $lot): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->fifo->voidLot($lot, $request->input('reason'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['lot' => $e->getMessage()]);
        }

        return redirect()
            ->route('inventory.stock.index')
            ->with('success', "Entrada {$lot->lot_number} anulada.");
    }
}
