<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Store;
use App\Services\ProductSalesReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductSalesController extends Controller
{
    public function __construct(private ProductSalesReportService $report) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('inventory.sold.index', [
            'rows' => $this->report->query($filters)->paginate(50)->withQueryString(),
            'totals' => $this->report->totals($filters),
            'filters' => $filters,
            'periods' => ProductSalesReportService::PERIODS,
            'sorts' => ProductSalesReportService::SORTS,
            'channels' => Sale::CHANNEL_LABELS,
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'canSeeCosts' => $request->user()->can('inventory.costs'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $canSeeCosts = $request->user()->can('inventory.costs');
        $rows = $this->report->query($filters)->get();
        $name = 'vendidos-por-producto-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows, $canSeeCosts) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $header = ['Código', 'Producto', 'Unidades vendidas', 'En combos', 'Devoluciones', 'Ventas', 'Ingreso (c/IVA)'];
            if ($canSeeCosts) {
                array_push($header, 'Costo', 'Margen');
            }
            array_push($header, 'Stock actual', 'Última venta');
            fputcsv($out, $header);

            foreach ($rows as $row) {
                $line = [
                    $row->code, $row->name, (int) $row->units_sold, (int) $row->units_in_combos,
                    (int) $row->units_returned, (int) $row->sales_count, number_format((float) $row->revenue, 2, '.', ''),
                ];
                if ($canSeeCosts) {
                    array_push($line, number_format((float) $row->cogs, 2, '.', ''), number_format((float) $row->revenue - (float) $row->cogs, 2, '.', ''));
                }
                array_push($line, (int) $row->stock_on_hand, $row->last_sold_at ? substr((string) $row->last_sold_at, 0, 10) : '');
                fputcsv($out, $line);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{period: string, from: ?\Carbon\CarbonImmutable, to: ?\Carbon\CarbonImmutable, from_input: ?string, to_input: ?string, channel: ?string, store_id: ?int, q: ?string, include_unsold: bool, sort: string}
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(array_keys(ProductSalesReportService::PERIODS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'channel' => ['nullable', Rule::in(array_keys(Sale::CHANNEL_LABELS))],
            'store_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'include_unsold' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(array_keys(ProductSalesReportService::SORTS))],
        ]);

        $period = $data['period'] ?? (isset($data['from']) || isset($data['to']) ? 'custom' : 'all');
        [$from, $to] = $this->report->range($period, $data['from'] ?? null, $data['to'] ?? null);

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'from_input' => $data['from'] ?? null,
            'to_input' => $data['to'] ?? null,
            'channel' => $data['channel'] ?? null,
            'store_id' => isset($data['store_id']) ? (int) $data['store_id'] : null,
            'q' => filled($data['q'] ?? null) ? trim($data['q']) : null,
            'include_unsold' => (bool) ($data['include_unsold'] ?? false),
            'sort' => $data['sort'] ?? 'units',
        ];
    }
}
