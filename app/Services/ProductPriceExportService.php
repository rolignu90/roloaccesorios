<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductPriceExportService
{
    public const HEADERS = [
        'CODIGO',
        'NOMBRE',
        'PRECIO C/IVA',
        'PRECIO DESCUENTO',
    ];

    public function queryFromRequest(Request $request): Builder
    {
        return Product::query()
            ->when(! $request->boolean('show_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = $request->string('q')->toString();
                $query->where(function ($inner) use ($term) {
                    $inner->where('code', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%");
                });
            })
            ->orderBy('name');
    }

    public function rows(Collection $products): Collection
    {
        return $products->map(function (Product $product) {
            return [
                'CODIGO' => $product->code,
                'NOMBRE' => $product->name,
                'PRECIO C/IVA' => number_format($product->salePriceWithVat(), 2, '.', ''),
                'PRECIO DESCUENTO' => $product->hasActivePromo()
                    ? number_format($product->effectiveSalePriceWithVat(), 2, '.', '')
                    : '',
            ];
        })->values();
    }

    public function downloadCsv(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
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

    public function downloadFromRequest(Request $request): StreamedResponse
    {
        $products = $this->queryFromRequest($request)->get();

        return $this->downloadCsv(
            $this->rows($products),
            'productos_precios_'.now()->format('Y-m-d_His').'.csv'
        );
    }
}
