<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Seller;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AccountingOverviewService
{
    public function __construct(private CostReportService $costs) {}

    /**
     * Balances operativos del período a partir de ventas (por estado).
     *
     * @return array<string, mixed>
     */
    public function balances(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        $sales = Sale::query()
            ->where('status', '!=', Sale::STATUS_VOIDED)
            ->whereBetween('sold_at', [$from, $to])
            ->get([
                'id',
                'status',
                'total',
                'taxable_base',
                'vat_amount',
                'shipping_amount',
                'cogs_total',
                'carrier_shipping_cost',
                'carrier_commission_amount',
                'customer_id',
                'seller_id',
            ]);

        $bucket = function (string $status) use ($sales): array {
            $rows = $sales->where('status', $status)->values();

            return [
                'status' => $status,
                'label' => Sale::STATUS_LABELS[$status] ?? $status,
                'count' => $rows->count(),
                'total_with_vat' => round((float) $rows->sum('total'), 2),
                'products_with_vat' => round((float) $rows->sum(fn (Sale $s) => (float) $s->taxable_base + (float) $s->vat_amount), 2),
                'shipping_charged' => round((float) $rows->sum('shipping_amount'), 2),
                'cogs_total' => round((float) $rows->sum('cogs_total'), 2),
            ];
        };

        $confirmed = $bucket(Sale::STATUS_CONFIRMED);
        $inTransit = $bucket(Sale::STATUS_IN_TRANSIT);
        $delivered = $bucket(Sale::STATUS_DELIVERED);
        $returned = $bucket(Sale::STATUS_RETURNED);

        $open = [
            'count' => $confirmed['count'] + $inTransit['count'],
            'total_with_vat' => round($confirmed['total_with_vat'] + $inTransit['total_with_vat'], 2),
            'label' => 'En proceso (confirmadas + en ruta)',
        ];

        $costSummary = $this->costs->summary($from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'by_status' => [
                Sale::STATUS_CONFIRMED => $confirmed,
                Sale::STATUS_IN_TRANSIT => $inTransit,
                Sale::STATUS_DELIVERED => $delivered,
                Sale::STATUS_RETURNED => $returned,
            ],
            'open' => $open,
            'delivered' => $delivered,
            'returned' => $returned,
            'activity_count' => $sales->count(),
            'activity_total_with_vat' => round((float) $sales->sum('total'), 2),
            'expected_net_with_vat' => $costSummary['expected']['net_result_with_vat'] ?? $costSummary['net_result_with_vat'],
            'realized_net_with_vat' => $costSummary['realized']['net_result_with_vat'] ?? 0,
            'pending_margin_with_vat' => $costSummary['pending']['real_margin_with_vat'] ?? 0,
            'expenses_total' => $costSummary['expenses_total'] ?? 0,
            'returns_loss' => $costSummary['returns_loss'] ?? 0,
        ];
    }

    /**
     * Saldos agregados por cliente (periodo).
     *
     * @return Collection<int, object>
     */
    public function balancesByCustomer(?Carbon $from = null, ?Carbon $to = null, ?int $limit = 50): Collection
    {
        return $this->balancesByParty('customer', $from, $to, $limit);
    }

    /**
     * Saldos agregados por vendedor (periodo).
     *
     * @return Collection<int, object>
     */
    public function balancesBySeller(?Carbon $from = null, ?Carbon $to = null, ?int $limit = 50): Collection
    {
        return $this->balancesByParty('seller', $from, $to, $limit);
    }

    /**
     * Estado de cuenta de un cliente.
     *
     * @return array<string, mixed>
     */
    public function customerStatement(Customer $customer, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return $this->partyStatement('customer', $customer->id, $from, $to, [
            'type' => 'customer',
            'id' => $customer->id,
            'code' => $customer->code,
            'name' => $customer->name,
            'phone' => $customer->phone,
        ]);
    }

    /**
     * Estado de cuenta de un vendedor.
     *
     * @return array<string, mixed>
     */
    public function sellerStatement(Seller $seller, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return $this->partyStatement('seller', $seller->id, $from, $to, [
            'type' => 'seller',
            'id' => $seller->id,
            'code' => $seller->code,
            'name' => $seller->name,
            'phone' => $seller->phone,
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function balancesByParty(string $party, ?Carbon $from, ?Carbon $to, ?int $limit): Collection
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();
        $fk = $party === 'seller' ? 'seller_id' : 'customer_id';

        $sales = Sale::query()
            ->where('status', '!=', Sale::STATUS_VOIDED)
            ->whereBetween('sold_at', [$from, $to])
            ->whereNotNull($fk)
            ->with($party === 'seller' ? 'seller:id,code,name' : 'customer:id,code,name,phone')
            ->orderByDesc('sold_at')
            ->get([
                'id',
                'number',
                'status',
                'total',
                'sold_at',
                'customer_id',
                'seller_id',
            ]);

        $grouped = $sales->groupBy($fk)->map(function (Collection $rows, $id) use ($party) {
            $open = $rows->whereIn('status', [Sale::STATUS_CONFIRMED, Sale::STATUS_IN_TRANSIT]);
            $delivered = $rows->where('status', Sale::STATUS_DELIVERED);
            $returned = $rows->where('status', Sale::STATUS_RETURNED);
            $model = $party === 'seller' ? $rows->first()?->seller : $rows->first()?->customer;

            return (object) [
                'party_id' => (int) $id,
                'code' => $model?->code,
                'name' => $model?->name,
                'phone' => $party === 'customer' ? ($model?->phone) : null,
                'sales_count' => $rows->count(),
                'open_count' => $open->count(),
                'open_total' => round((float) $open->sum('total'), 2),
                'delivered_count' => $delivered->count(),
                'delivered_total' => round((float) $delivered->sum('total'), 2),
                'returned_count' => $returned->count(),
                'returned_total' => round((float) $returned->sum('total'), 2),
                'activity_total' => round((float) $rows->sum('total'), 2),
            ];
        })
            ->sortByDesc(fn ($row) => $row->open_total + $row->activity_total)
            ->values();

        return $limit ? $grouped->take($limit)->values() : $grouped;
    }

    /**
     * @param  array<string, mixed>  $partyMeta
     * @return array<string, mixed>
     */
    private function partyStatement(string $party, int $partyId, ?Carbon $from, ?Carbon $to, array $partyMeta): array
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();
        $fk = $party === 'seller' ? 'seller_id' : 'customer_id';

        $sales = Sale::query()
            ->where($fk, $partyId)
            ->where('status', '!=', Sale::STATUS_VOIDED)
            ->whereBetween('sold_at', [$from, $to])
            ->with(['customer:id,code,name', 'seller:id,code,name', 'shippingCarrier:id,name'])
            ->orderByDesc('sold_at')
            ->get();

        $open = $sales->whereIn('status', [Sale::STATUS_CONFIRMED, Sale::STATUS_IN_TRANSIT]);
        $delivered = $sales->where('status', Sale::STATUS_DELIVERED);
        $returned = $sales->where('status', Sale::STATUS_RETURNED);

        $lines = $sales->map(fn (Sale $sale) => (object) [
            'id' => $sale->id,
            'number' => $sale->number,
            'sold_at' => $sale->sold_at,
            'status' => $sale->status,
            'status_label' => $sale->statusLabel(),
            'status_badge' => $sale->statusBadgeClass(),
            'total' => round((float) $sale->total, 2),
            'shipping_amount' => round((float) $sale->shipping_amount, 2),
            'payment_method' => $sale->payment_method,
            'channel' => $sale->channel,
            'customer' => $sale->customer?->name,
            'seller' => $sale->seller?->name,
            'carrier' => $sale->shippingCarrier?->name,
        ]);

        return [
            'party' => $partyMeta,
            'from' => $from,
            'to' => $to,
            'summary' => [
                'sales_count' => $sales->count(),
                'open_count' => $open->count(),
                'open_total' => round((float) $open->sum('total'), 2),
                'delivered_count' => $delivered->count(),
                'delivered_total' => round((float) $delivered->sum('total'), 2),
                'returned_count' => $returned->count(),
                'returned_total' => round((float) $returned->sum('total'), 2),
                'activity_total' => round((float) $sales->sum('total'), 2),
            ],
            'lines' => $lines,
        ];
    }
}
