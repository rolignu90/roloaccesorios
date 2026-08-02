<?php

namespace App\Services;

use App\Models\Consignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsignmentDashboardService
{
    /**
     * @return array{
     *     delivered: float,
     *     returned: float,
     *     paid: float,
     *     balance: float,
     *     open_count: int,
     *     settled_count: int,
     *     by_party: Collection,
     *     top_products: Collection,
     *     recent: Collection,
     *     aging: Collection
     * }
     */
    public function snapshot(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfDay();

        $base = Consignment::query()
            ->where('status', '!=', Consignment::STATUS_VOIDED)
            ->whereBetween('delivered_at', [$from, $to]);

        $delivered = (float) (clone $base)->sum('total_with_vat');
        $returned = (float) (clone $base)->sum('returned_with_vat');
        $paid = (float) (clone $base)->sum('paid_with_vat');
        $balance = (float) Consignment::query()
            ->where('status', '!=', Consignment::STATUS_VOIDED)
            ->sum('balance_with_vat');

        $openCount = (int) Consignment::query()
            ->whereIn('status', [Consignment::STATUS_OPEN, Consignment::STATUS_PARTIAL])
            ->count();
        $settledCount = (int) Consignment::query()
            ->where('status', Consignment::STATUS_SETTLED)
            ->whereBetween('delivered_at', [$from, $to])
            ->count();

        return [
            'delivered' => round($delivered, 2),
            'returned' => round($returned, 2),
            'paid' => round($paid, 2),
            'balance' => round($balance, 2),
            'open_count' => $openCount,
            'settled_count' => $settledCount,
            'by_party' => $this->byParty(),
            'top_products' => $this->topProducts(),
            'recent' => Consignment::query()
                ->with(['seller:id,code,name', 'customer:id,code,name'])
                ->latest('delivered_at')
                ->limit(12)
                ->get(),
            'aging' => $this->agingOpen(),
        ];
    }

    private function byParty(): Collection
    {
        $sellerRows = DB::table('consignments')
            ->join('sellers', 'sellers.id', '=', 'consignments.seller_id')
            ->where('consignments.status', '!=', Consignment::STATUS_VOIDED)
            ->where('consignments.party_type', Consignment::PARTY_SELLER)
            ->groupBy('sellers.id', 'sellers.code', 'sellers.name')
            ->select([
                'sellers.id as party_id',
                'sellers.code',
                'sellers.name',
                DB::raw("'Vendedor' as party_label"),
            ])
            ->selectRaw('SUM(consignments.total_with_vat) as delivered')
            ->selectRaw('SUM(consignments.paid_with_vat) as paid')
            ->selectRaw('SUM(consignments.balance_with_vat) as balance')
            ->havingRaw('SUM(consignments.balance_with_vat) > 0 OR SUM(consignments.total_with_vat) > 0')
            ->orderByDesc('balance')
            ->limit(10)
            ->get();

        $customerRows = DB::table('consignments')
            ->join('customers', 'customers.id', '=', 'consignments.customer_id')
            ->where('consignments.status', '!=', Consignment::STATUS_VOIDED)
            ->where('consignments.party_type', Consignment::PARTY_CUSTOMER)
            ->groupBy('customers.id', 'customers.code', 'customers.name')
            ->select([
                'customers.id as party_id',
                'customers.code',
                'customers.name',
                DB::raw("'Cliente' as party_label"),
            ])
            ->selectRaw('SUM(consignments.total_with_vat) as delivered')
            ->selectRaw('SUM(consignments.paid_with_vat) as paid')
            ->selectRaw('SUM(consignments.balance_with_vat) as balance')
            ->havingRaw('SUM(consignments.balance_with_vat) > 0 OR SUM(consignments.total_with_vat) > 0')
            ->orderByDesc('balance')
            ->limit(10)
            ->get();

        return $sellerRows->concat($customerRows)->sortByDesc('balance')->values()->take(12);
    }

    private function topProducts(): Collection
    {
        return DB::table('consignment_items')
            ->join('consignments', 'consignments.id', '=', 'consignment_items.consignment_id')
            ->join('products', 'products.id', '=', 'consignment_items.product_id')
            ->where('consignments.status', '!=', Consignment::STATUS_VOIDED)
            ->groupBy('products.id', 'products.code', 'products.name')
            ->select([
                'products.id',
                'products.code',
                'products.name',
            ])
            ->selectRaw('SUM(consignment_items.quantity - consignment_items.quantity_returned) as qty_out')
            ->selectRaw('SUM((consignment_items.quantity - consignment_items.quantity_returned) * consignment_items.unit_price_with_vat) as value_out')
            ->havingRaw('SUM(consignment_items.quantity - consignment_items.quantity_returned) > 0')
            ->orderByDesc('value_out')
            ->limit(10)
            ->get();
    }

    private function agingOpen(): Collection
    {
        return Consignment::query()
            ->with(['seller:id,name', 'customer:id,name'])
            ->whereIn('status', [Consignment::STATUS_OPEN, Consignment::STATUS_PARTIAL])
            ->where('balance_with_vat', '>', 0)
            ->orderBy('delivered_at')
            ->limit(15)
            ->get()
            ->map(function (Consignment $c) {
                $c->setAttribute('days_open', $c->delivered_at?->diffInDays(now()) ?? 0);

                return $c;
            });
    }
}
