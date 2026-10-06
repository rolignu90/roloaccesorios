<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleReportExportService
{
    public const HEADERS = [
        'Numero venta',
        'Fecha',
        'Estado',
        'Vendedor codigo',
        'Vendedor',
        'Cliente codigo',
        'Cliente',
        'Telefono',
        'Departamento',
        'Municipio',
        'Producto codigo',
        'Producto',
        'Combo',
        'Cantidad',
        'Precio unitario c/IVA',
        'Total linea c/IVA',
        'Costo linea (COGS)',
        'Ganancia linea',
        'Envio cobrado',
        'Total venta',
        'Costo venta (COGS)',
        'Ganancia producto venta',
        'Metodo pago',
        'Canal',
    ];

    public function downloadFromRequest(Request $request): StreamedResponse
    {
        $rows = $this->rowsFromRequest($request);
        $from = $request->input('from') ?: 'inicio';
        $to = $request->input('to') ?: 'hoy';
        $filename = 'reporte_ventas_'.$from.'_'.$to.'_'.now()->format('Ymd_His').'.csv';

        return $this->downloadCsv($rows, $filename);
    }

    public function rowsFromRequest(Request $request): Collection
    {
        $sales = $this->filteredQuery($request)
            ->with([
                'customer:id,code,name,phone,department,municipality',
                'seller:id,code,name',
                'items.product:id,code,name',
                'items.combo:id,code,name',
            ])
            ->orderBy('sold_at')
            ->orderBy('id')
            ->get();

        $paymentMethods = config('sales.payment_methods', []);
        $rows = collect();

        foreach ($sales as $sale) {
            $paymentLabel = $paymentMethods[$sale->payment_method] ?? (string) $sale->payment_method;
            $saleMargin = $sale->grossMarginWithVat();
            $channel = (string) ($sale->channel ?? 'crm');

            if ($sale->items->isEmpty()) {
                $rows->push($this->mapRow($sale, null, $paymentLabel, $saleMargin, $channel));

                continue;
            }

            foreach ($sale->items as $item) {
                $rows->push($this->mapRow($sale, $item, $paymentLabel, $saleMargin, $channel));
            }
        }

        return $rows;
    }

    public function filteredQuery(Request $request): Builder
    {
        return Sale::query()
            ->visibleTo($request->user())
            ->when($request->boolean('stuck_in_transit'), fn ($q) => $q->stuckInTransit(7))
            ->when($request->boolean('pending_sync'), fn ($q) => $q->pendingSistrackStatusSync())
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('number', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('seller', fn ($s) => $s->where('name', 'like', "%{$term}%"));
                });
            })
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
                fn ($q) => $q->where('status', '!=', Sale::STATUS_VOIDED)
            )
            ->when($request->filled('seller_id'), fn ($q) => $q->where('seller_id', $request->integer('seller_id')))
            ->when(
                array_key_exists($request->string('channel')->toString(), Sale::CHANNEL_LABELS),
                fn ($q) => $q->where('channel', $request->string('channel')->toString())
            )
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', $request->integer('store_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('sold_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('sold_at', '<=', $request->date('to')))
            ->when($request->filled('shipping'), function ($query) use ($request) {
                $shipping = $request->string('shipping')->toString();
                if ($shipping === 'with') {
                    $query->where('has_shipping', true);
                } elseif ($shipping === 'without') {
                    $query->where('has_shipping', false);
                }
            });
    }

    /**
     * @param  SaleItem|null  $item
     * @return array<string, scalar|null>
     */
    private function mapRow(Sale $sale, $item, string $paymentLabel, float $saleMargin, string $channel): array
    {
        $lineTotal = $item ? (float) $item->line_total : 0.0;
        $lineCogs = $item ? (float) $item->cogs_total : 0.0;
        $unitWithVat = 0.0;
        if ($item && (int) $item->quantity > 0) {
            $unitWithVat = round($lineTotal / (int) $item->quantity, 2);
        }

        return [
            'Numero venta' => $sale->number,
            'Fecha' => optional($sale->sold_at)->format('Y-m-d H:i'),
            'Estado' => $sale->statusLabel(),
            'Vendedor codigo' => $sale->seller?->code,
            'Vendedor' => $sale->seller?->name,
            'Cliente codigo' => $sale->customer?->code,
            'Cliente' => $sale->customer?->name,
            'Telefono' => $sale->customer?->phone,
            'Departamento' => $sale->customer?->department,
            'Municipio' => $sale->customer?->municipality,
            'Producto codigo' => $item?->product?->code,
            'Producto' => $item?->product?->name,
            'Combo' => $item?->combo ? ($item->combo->code.' — '.$item->combo->name) : '',
            'Cantidad' => $item?->quantity ?? 0,
            'Precio unitario c/IVA' => number_format($unitWithVat, 2, '.', ''),
            'Total linea c/IVA' => number_format($lineTotal, 2, '.', ''),
            'Costo linea (COGS)' => number_format($lineCogs, 2, '.', ''),
            'Ganancia linea' => number_format(round($lineTotal - $lineCogs, 2), 2, '.', ''),
            'Envio cobrado' => number_format((float) $sale->shipping_amount, 2, '.', ''),
            'Total venta' => number_format((float) $sale->total, 2, '.', ''),
            'Costo venta (COGS)' => number_format((float) $sale->cogs_total, 2, '.', ''),
            'Ganancia producto venta' => number_format($saleMargin, 2, '.', ''),
            'Metodo pago' => $paymentLabel,
            'Canal' => $channel,
        ];
    }

    public function downloadCsv(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::HEADERS);

            foreach ($rows as $row) {
                $ordered = [];
                foreach (self::HEADERS as $header) {
                    $ordered[] = $row[$header] ?? '';
                }
                fputcsv($handle, $ordered);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
