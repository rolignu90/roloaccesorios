<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Consignment;
use App\Models\ConsignmentLotAllocation;
use App\Models\Product;
use App\Models\SaleLotAllocation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class OnDemandSaleController extends Controller
{
    public function index(Request $request): View
    {
        $source = $request->string('source')->toString();
        if (! in_array($source, ['', 'sale', 'consignment'], true)) {
            $source = '';
        }

        $saleLines = $source === 'consignment'
            ? collect()
            : $this->pendingSaleLines($request);

        $consignmentLines = $source === 'sale'
            ? collect()
            : $this->pendingConsignmentLines($request);

        $lines = $saleLines
            ->concat($consignmentLines)
            ->sortByDesc(fn (array $row) => $row['occurred_at_sort'].'|'.$row['id'])
            ->values();

        $summary = $lines
            ->groupBy('product_id')
            ->map(function (Collection $group) {
                $first = $group->first();

                return (object) [
                    'product_id' => $first['product_id'],
                    'code' => $first['product_code'],
                    'name' => $first['product_name'],
                    'qty_pending' => (int) $group->sum('quantity'),
                    'cogs_estimated' => round((float) $group->sum('cogs_amount'), 2),
                    'from_sales' => (int) $group->where('source', 'sale')->sum('quantity'),
                    'from_consignments' => (int) $group->where('source', 'consignment')->sum('quantity'),
                ];
            })
            ->sortByDesc('qty_pending')
            ->values();

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 30;
        $paginator = new LengthAwarePaginator(
            $lines->forPage($page, $perPage)->values(),
            $lines->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $products = Product::query()
            ->where('on_demand', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('inventory.on-demand.index', [
            'summary' => $summary,
            'lines' => $paginator,
            'products' => $products,
            'totalQty' => (int) $summary->sum('qty_pending'),
            'totalCogs' => round((float) $summary->sum('cogs_estimated'), 2),
            'totalFromSales' => (int) $summary->sum('from_sales'),
            'totalFromConsignments' => (int) $summary->sum('from_consignments'),
            'source' => $source,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function pendingSaleLines(Request $request): Collection
    {
        return SaleLotAllocation::query()
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
            })
            ->with([
                'saleItem.product',
                'saleItem.sale.customer',
            ])
            ->get()
            ->map(function (SaleLotAllocation $line) {
                $item = $line->saleItem;
                $sale = $item?->sale;
                $product = $item?->product;

                return [
                    'id' => 's-'.$line->id,
                    'source' => 'sale',
                    'occurred_at' => $sale?->sold_at,
                    'occurred_at_sort' => optional($sale?->sold_at)->format('Y-m-d H:i:s') ?? '',
                    'document_number' => $sale?->number,
                    'document_url' => $sale ? route('sales.sales.show', $sale) : null,
                    'status_label' => $sale?->statusLabel(),
                    'status_badge' => $sale?->statusBadgeClass(),
                    'party_name' => $sale?->customer?->name ?? '—',
                    'product_id' => (int) ($product?->id ?? 0),
                    'product_code' => $product?->code,
                    'product_name' => $product?->name,
                    'quantity' => (int) $line->quantity,
                    'purchase_price' => (float) $line->purchase_price,
                    'cogs_amount' => (float) $line->cogs_amount,
                ];
            })
            ->filter(fn (array $row) => $row['product_id'] > 0 && $row['quantity'] > 0)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function pendingConsignmentLines(Request $request): Collection
    {
        // Filtro de estado de venta no aplica a consignaciones.
        if ($request->filled('status')) {
            return collect();
        }

        return ConsignmentLotAllocation::query()
            ->whereNull('consignment_lot_allocations.inventory_lot_id')
            ->whereHas('consignmentItem.consignment', function ($query) use ($request) {
                $query->where('status', '!=', Consignment::STATUS_VOIDED)
                    ->when($request->filled('from'), fn ($q) => $q->whereDate('delivered_at', '>=', $request->date('from')))
                    ->when($request->filled('to'), fn ($q) => $q->whereDate('delivered_at', '<=', $request->date('to')));
            })
            ->when($request->filled('product_id'), function ($query) use ($request) {
                $query->whereHas('consignmentItem', fn ($q) => $q->where('product_id', $request->integer('product_id')));
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->whereHas('consignmentItem.product', function ($product) use ($term) {
                        $product->where('code', 'like', "%{$term}%")
                            ->orWhere('name', 'like', "%{$term}%");
                    })->orWhereHas('consignmentItem.consignment', function ($consignment) use ($term) {
                        $consignment->where('number', 'like', "%{$term}%");
                    })->orWhereHas('consignmentItem.consignment.customer', function ($customer) use ($term) {
                        $customer->where('name', 'like', "%{$term}%");
                    })->orWhereHas('consignmentItem.consignment.seller', function ($seller) use ($term) {
                        $seller->where('name', 'like', "%{$term}%");
                    });
                });
            })
            ->with([
                'consignmentItem.product',
                'consignmentItem.consignment.customer',
                'consignmentItem.consignment.seller',
            ])
            ->get()
            ->map(function (ConsignmentLotAllocation $line) {
                $qty = $line->quantityOutstanding();
                if ($qty <= 0) {
                    return null;
                }

                $item = $line->consignmentItem;
                $consignment = $item?->consignment;
                $product = $item?->product;
                $unitCogs = $qty * (float) $line->purchase_price;

                return [
                    'id' => 'c-'.$line->id,
                    'source' => 'consignment',
                    'occurred_at' => $consignment?->delivered_at,
                    'occurred_at_sort' => optional($consignment?->delivered_at)->format('Y-m-d H:i:s') ?? '',
                    'document_number' => $consignment?->number,
                    'document_url' => $consignment ? route('consignments.show', $consignment) : null,
                    'status_label' => $consignment ? (Consignment::STATUSES[$consignment->status] ?? $consignment->status) : null,
                    'status_badge' => match ($consignment?->status) {
                        Consignment::STATUS_SETTLED => 'badge-ok',
                        Consignment::STATUS_PARTIAL => 'badge-warn',
                        Consignment::STATUS_OPEN => 'badge-warn',
                        default => '',
                    },
                    'party_name' => $consignment?->partyLabel() ?? '—',
                    'product_id' => (int) ($product?->id ?? 0),
                    'product_code' => $product?->code,
                    'product_name' => $product?->name,
                    'quantity' => $qty,
                    'purchase_price' => (float) $line->purchase_price,
                    'cogs_amount' => round($unitCogs, 2),
                ];
            })
            ->filter()
            ->filter(fn (array $row) => $row['product_id'] > 0 && $row['quantity'] > 0)
            ->values();
    }
}
