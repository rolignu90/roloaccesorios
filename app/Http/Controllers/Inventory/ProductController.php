<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BulkUpdateProductPricesRequest;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Http\Requests\Inventory\UpdateProductRequest;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with(['productSuppliers.supplier'])
            ->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')
            ->when(! $request->boolean('show_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('inventory.products.index', compact('products'));
    }

    public function create(): View
    {
        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('inventory.products.create', compact('suppliers'));
    }

    public function duplicate(Product $product): View
    {
        $product->load('productSuppliers');

        $duplicate = $product->replicate();
        $duplicate->code = $this->uniqueCopyCode($product->code);
        $duplicate->name = trim($product->name).' (copia)';
        $duplicate->setRelation(
            'productSuppliers',
            $product->productSuppliers->map(fn ($row) => $row->replicate())->values()
        );

        $supplierIds = $product->productSuppliers->pluck('supplier_id');

        $suppliers = Supplier::query()
            ->where(function ($query) use ($supplierIds) {
                $query->where('is_active', true)
                    ->orWhereIn('id', $supplierIds);
            })
            ->orderBy('name')
            ->get();

        return view('inventory.products.create', [
            'product' => $duplicate,
            'suppliers' => $suppliers,
            'stockOnHand' => 0,
            'sourceProduct' => $product,
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['suppliers', 'sale_price_with_vat', 'wholesale_price_with_vat']);
        $data['sale_price_without_vat'] = price_without_vat($request->input('sale_price_with_vat', 0));
        $wholesale = $request->input('wholesale_price_with_vat');
        $data['wholesale_price_without_vat'] = ($wholesale === null || $wholesale === '')
            ? null
            : price_without_vat($wholesale);
        if (! ($data['promo_active'] ?? false)) {
            $data['promo_active'] = false;
            $data['promo_type'] = null;
            $data['promo_value'] = null;
        }

        DB::transaction(function () use ($request, $data) {
            $product = Product::query()->create($data);
            $product->syncSuppliers($request->input('suppliers', []));
        });

        return redirect()
            ->route('inventory.products.index')
            ->with('success', 'Producto creado correctamente.');
    }

    public function show(Product $product): View
    {
        $product->load([
            'productSuppliers.supplier',
            'inventoryLots' => fn ($q) => $q->with('supplier')->orderByDesc('received_at')->orderByDesc('id'),
        ]);

        $stockOnHand = $product->stockOnHand();

        return view('inventory.products.show', compact('product', 'stockOnHand'));
    }

    public function edit(Product $product): View
    {
        $product->load('productSuppliers')
            ->loadSum('inventoryLots as stock_on_hand', 'quantity_remaining');

        $suppliers = Supplier::query()
            ->orderBy('name')
            ->get();

        return view('inventory.products.edit', [
            'product' => $product,
            'suppliers' => $suppliers,
            'stockOnHand' => (int) ($product->stock_on_hand ?? 0),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->safe()->except(['suppliers', 'sale_price_with_vat', 'wholesale_price_with_vat']);
        $data['sale_price_without_vat'] = price_without_vat($request->input('sale_price_with_vat', 0));
        $wholesale = $request->input('wholesale_price_with_vat');
        $data['wholesale_price_without_vat'] = ($wholesale === null || $wholesale === '')
            ? null
            : price_without_vat($wholesale);
        if (! ($data['promo_active'] ?? false)) {
            $data['promo_active'] = false;
            $data['promo_type'] = null;
            $data['promo_value'] = null;
        }

        DB::transaction(function () use ($request, $product, $data) {
            $product->update($data);
            $product->syncSuppliers($request->input('suppliers', []));
        });

        return redirect()
            ->route('inventory.products.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->hasSalesHistory()) {
            $product->update(['is_active' => false]);

            return redirect()
                ->route('inventory.products.index')
                ->with('success', 'El producto tiene ventas o lotes usados; se desactivó para conservar el historial. Ya no aparecerá en ventas nuevas.');
        }

        DB::transaction(function () use ($product) {
            $product->productSuppliers()->delete();
            $product->inventoryLots()->delete();
            $product->delete();
        });

        return redirect()
            ->route('inventory.products.index')
            ->with('success', 'Producto eliminado correctamente.');
    }

    public function bulkPrices(Request $request): View
    {
        $products = Product::query()
            ->when(! $request->boolean('show_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->get();

        return view('inventory.products.bulk-prices', [
            'products' => $products,
            'vatRate' => config('sales.vat_rate'),
        ]);
    }

    public function updateBulkPrices(BulkUpdateProductPricesRequest $request): RedirectResponse
    {
        $rows = $request->validated('products');
        $updated = 0;

        DB::transaction(function () use ($rows, &$updated) {
            foreach ($rows as $row) {
                $product = Product::query()->lockForUpdate()->findOrFail($row['id']);

                $product->update([
                    'sale_price_without_vat' => price_without_vat($row['sale_price_with_vat']),
                    'wholesale_price_without_vat' => $row['wholesale_price_with_vat'] === null
                        ? null
                        : price_without_vat($row['wholesale_price_with_vat']),
                    'promo_active' => (bool) ($row['promo_active'] ?? false),
                    'promo_type' => ($row['promo_active'] ?? false) ? ($row['promo_type'] ?? Product::PROMO_AMOUNT) : null,
                    'promo_value' => ($row['promo_active'] ?? false) ? $row['promo_value'] : null,
                ]);
                $updated++;
            }
        });

        return redirect()
            ->route('inventory.products.bulk-prices', array_filter([
                'q' => $request->query('q'),
                'show_inactive' => $request->query('show_inactive'),
            ]))
            ->with('success', "Precios actualizados en {$updated} producto".($updated === 1 ? '' : 's').'.');
    }

    private function uniqueCopyCode(string $baseCode): string
    {
        $base = strtoupper(trim($baseCode));
        $candidate = $base.'-COPIA';
        $suffix = 2;

        while (Product::query()->where('code', $candidate)->exists()) {
            $candidate = $base.'-COPIA'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
