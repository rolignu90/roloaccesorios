<?php

namespace App\Services;

use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaleLabelExportService
{
    /**
     * Headers matching modelo_carga.xlsx for label generation systems.
     */
    public const HEADERS = [
        'ORDEN',
        'NOMBRE',
        'TELEFONO',
        'EMAIL',
        'DIRECCION',
        'MUNICIPIO',
        'DEPARTAMENTO',
        'PAIS',
        'CODIGO POSTAL',
        'DESCRIPCION',
        'PESO',
        'PRECIO',
        'OBSERVACIONES',
    ];

    public function rowsForDate(Carbon $date): Collection
    {
        $sales = Sale::query()
            ->with(['customer', 'seller', 'items.product'])
            ->revenue()
            ->where('has_shipping', true)
            ->where(function ($query) {
                $query->whereNull('sistrack_status')
                    ->orWhere('sistrack_status', '!=', Sale::SISTRACK_SENT);
            })
            ->whereDate('sold_at', $date->toDateString())
            ->orderBy('sold_at')
            ->get();

        return $this->rowsFromSales($sales);
    }

    /**
     * @param  list<int|string>  $saleIds
     */
    public function rowsForIds(array $saleIds): Collection
    {
        $ids = collect($saleIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        $sales = Sale::query()
            ->with(['customer', 'seller', 'items.product'])
            ->whereIn('id', $ids)
            ->revenue()
            ->where('has_shipping', true)
            ->where(function ($query) {
                $query->whereNull('sistrack_status')
                    ->orWhere('sistrack_status', '!=', Sale::SISTRACK_SENT);
            })
            ->orderBy('sold_at')
            ->get();

        return $this->rowsFromSales($sales);
    }

    public function downloadCsv(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens accents correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::HEADERS);

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadCsvForDate(Carbon $date): StreamedResponse
    {
        return $this->downloadCsv(
            $this->rowsForDate($date),
            'modelo_carga_'.$date->format('Y-m-d').'.csv'
        );
    }

    public function downloadCsvForIds(array $saleIds): StreamedResponse
    {
        return $this->downloadCsv(
            $this->rowsForIds($saleIds),
            'modelo_carga_seleccion_'.now()->format('Y-m-d_His').'.csv'
        );
    }

    private function rowsFromSales(Collection $sales): Collection
    {
        return $sales->map(fn (Sale $sale) => $this->mapSale($sale));
    }

    public function mapSale(Sale $sale): array
    {
        $customer = $sale->customer;
        $prefix = rtrim((string) ($sale->seller?->saleNumberPrefix() ?? 'V-'), '-');
        if ($prefix === '') {
            $prefix = 'V';
        }

        $saleTail = substr((string) $sale->number, -4);

        $itemsDescription = $sale->items
            ->map(function ($item) {
                $name = trim((string) ($item->product?->name ?? 'Producto'));

                return ((int) $item->quantity).' '.$name;
            })
            ->filter()
            ->implode(', ');

        $description = $saleTail.' | '.$prefix.' - '.$itemsDescription;

        $weight = $sale->items->sum(function ($item) {
            $unitWeight = (float) ($item->product?->weight ?? 0);

            return $unitWeight * $item->quantity;
        });

        return [
            'ORDEN' => $sale->number,
            'NOMBRE' => $customer?->name ?? '',
            'TELEFONO' => $customer?->phone ?? '',
            'EMAIL' => $customer?->email ?? '',
            'DIRECCION' => $customer?->address ?? '',
            'MUNICIPIO' => $customer?->municipality ?? '',
            'DEPARTAMENTO' => $customer?->department ?? '',
            'PAIS' => $customer?->country ?: 'El Salvador',
            'CODIGO POSTAL' => $customer?->postal_code ?? '',
            'DESCRIPCION' => $description,
            'PESO' => $weight > 0 ? number_format($weight, 3, '.', '') : '',
            'PRECIO' => number_format((float) $sale->total, 2, '.', ''),
            'OBSERVACIONES' => trim((string) ($sale->notes ?? '')),
        ];
    }
}
