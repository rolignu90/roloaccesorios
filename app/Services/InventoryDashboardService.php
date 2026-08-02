<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryDashboardService
{
    /**
     * @return array{
     *     inventory_value: float,
     *     retail_value: float,
     *     units_on_hand: int,
     *     active_products: int,
     *     out_of_stock: int,
     *     low_stock_count: int,
     *     purchases_this_month: array{amount: float, units: int, receipts: int},
     *     purchases_last_month: array{amount: float, units: int, receipts: int},
     *     purchases_change_percent: float|null,
     *     purchase_months: Collection<int, object>,
     *     low_stock_products: Collection<int, Product>,
     *     top_by_value: Collection<int, object>,
     *     purchases_by_supplier: Collection<int, object>,
     *     recent_movements: Collection<int, InventoryMovement>
     * }
     */
    public function snapshot(?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy();
        $thisMonthStart = $now->copy()->startOfMonth();
        $thisMonthEnd = $now->copy()->endOfMonth();
        $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();
        $trendStart = $now->copy()->subMonthsNoOverflow(5)->startOfMonth();

        $inventoryValue = (float) InventoryLot::query()
            ->where('quantity_remaining', '>', 0)
            ->sum(DB::raw('quantity_remaining * purchase_price'));

        $unitsOnHand = (int) InventoryLot::query()->sum('quantity_remaining');

        $vatRate = (float) config('sales.vat_rate', 0.13);
        $retailValue = (float) DB::table('inventory_lots')
            ->join('products', 'products.id', '=', 'inventory_lots.product_id')
            ->where('inventory_lots.quantity_remaining', '>', 0)
            ->selectRaw(
                'COALESCE(SUM(inventory_lots.quantity_remaining * products.sale_price_without_vat * ?), 0) as total',
                [1 + $vatRate]
            )
            ->value('total');

        $activeProducts = (int) Product::query()->where('is_active', true)->count();

        $stockByProduct = Product::query()
            ->where('is_active', true)
            ->with([
                'productSuppliers' => fn ($query) => $query
                    ->with('supplier:id,code,name')
                    ->orderByDesc('is_preferred'),
            ])
            ->withSum('inventoryLots as stock_on_hand', 'quantity_remaining')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'min_stock', 'unit']);

        $outOfStock = $stockByProduct->filter(fn (Product $p) => (int) ($p->stock_on_hand ?? 0) <= 0)->count();

        $lowStockProducts = $stockByProduct
            ->filter(fn (Product $p) => $p->isBelowMinStock((int) ($p->stock_on_hand ?? 0)))
            ->values();

        $purchasesThisMonth = $this->purchaseStats($thisMonthStart, $thisMonthEnd);
        $purchasesLastMonth = $this->purchaseStats($lastMonthStart, $lastMonthEnd);

        $changePercent = null;
        if ($purchasesLastMonth['amount'] > 0) {
            $changePercent = round(
                (($purchasesThisMonth['amount'] - $purchasesLastMonth['amount']) / $purchasesLastMonth['amount']) * 100,
                1
            );
        } elseif ($purchasesThisMonth['amount'] > 0) {
            $changePercent = 100.0;
        }

        return [
            'inventory_value' => round($inventoryValue, 2),
            'retail_value' => round($retailValue, 2),
            'units_on_hand' => $unitsOnHand,
            'active_products' => $activeProducts,
            'out_of_stock' => $outOfStock,
            'low_stock_count' => $lowStockProducts->count(),
            'purchases_this_month' => $purchasesThisMonth,
            'purchases_last_month' => $purchasesLastMonth,
            'purchases_change_percent' => $changePercent,
            'purchase_months' => $this->purchaseMonths($trendStart, $thisMonthEnd),
            'low_stock_products' => $lowStockProducts,
            'top_by_value' => $this->topProductsByValue(10),
            'purchases_by_supplier' => $this->purchasesBySupplier($thisMonthStart, $thisMonthEnd),
            'recent_movements' => InventoryMovement::query()
                ->with(['product:id,code,name', 'inventoryLot:id,lot_number'])
                ->latest('occurred_at')
                ->latest('id')
                ->limit(12)
                ->get(),
        ];
    }

    /**
     * @return array{amount: float, units: int, receipts: int}
     */
    private function purchaseStats(Carbon $from, Carbon $to): array
    {
        $row = InventoryLot::query()
            ->whereBetween('received_at', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(quantity_received * purchase_price), 0) as amount')
            ->selectRaw('COALESCE(SUM(quantity_received), 0) as units')
            ->selectRaw('COUNT(*) as receipts')
            ->first();

        return [
            'amount' => round((float) ($row->amount ?? 0), 2),
            'units' => (int) ($row->units ?? 0),
            'receipts' => (int) ($row->receipts ?? 0),
        ];
    }

    /**
     * @return Collection<int, object{month_key: string, label: string, amount: float, units: int, receipts: int}>
     */
    private function purchaseMonths(Carbon $from, Carbon $to): Collection
    {
        $rows = InventoryLot::query()
            ->whereBetween('received_at', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("DATE_FORMAT(received_at, '%Y-%m') as month_key")
            ->selectRaw('COALESCE(SUM(quantity_received * purchase_price), 0) as amount')
            ->selectRaw('COALESCE(SUM(quantity_received), 0) as units')
            ->selectRaw('COUNT(*) as receipts')
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get()
            ->keyBy('month_key');

        $months = collect();
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $row = $rows->get($key);
            $months->push((object) [
                'month_key' => $key,
                'label' => $cursor->locale('es')->isoFormat('MMM YYYY'),
                'amount' => round((float) ($row->amount ?? 0), 2),
                'units' => (int) ($row->units ?? 0),
                'receipts' => (int) ($row->receipts ?? 0),
            ]);
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * @return Collection<int, object>
     */
    private function topProductsByValue(int $limit): Collection
    {
        return DB::table('inventory_lots')
            ->join('products', 'products.id', '=', 'inventory_lots.product_id')
            ->where('inventory_lots.quantity_remaining', '>', 0)
            ->groupBy('products.id', 'products.code', 'products.name', 'products.unit')
            ->select([
                'products.id',
                'products.code',
                'products.name',
                'products.unit',
            ])
            ->selectRaw('SUM(inventory_lots.quantity_remaining) as qty')
            ->selectRaw('SUM(inventory_lots.quantity_remaining * inventory_lots.purchase_price) as inventory_value')
            ->orderByDesc('inventory_value')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function purchasesBySupplier(Carbon $from, Carbon $to): Collection
    {
        return DB::table('inventory_lots')
            ->join('suppliers', 'suppliers.id', '=', 'inventory_lots.supplier_id')
            ->whereBetween('inventory_lots.received_at', [$from->toDateString(), $to->toDateString()])
            ->groupBy('suppliers.id', 'suppliers.code', 'suppliers.name')
            ->select([
                'suppliers.id',
                'suppliers.code',
                'suppliers.name',
            ])
            ->selectRaw('SUM(inventory_lots.quantity_received * inventory_lots.purchase_price) as amount')
            ->selectRaw('SUM(inventory_lots.quantity_received) as units')
            ->selectRaw('COUNT(*) as receipts')
            ->orderByDesc('amount')
            ->limit(8)
            ->get();
    }
}
