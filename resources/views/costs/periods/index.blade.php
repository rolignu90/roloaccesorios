@extends('layouts.app')

@section('title', 'Ventas por período')

@section('content')
@php
    $groupLabels = [
        'day' => 'Día',
        'week' => 'Semana',
        'month' => 'Mes',
    ];
    $chartPayload = [
        'labels' => $rows->pluck('label')->values()->all(),
        'salesCount' => $rows->pluck('sales_count')->map(fn ($v) => (int) $v)->values()->all(),
        'salesWithVat' => $rows->pluck('sales_total_with_vat')->map(fn ($v) => (float) $v)->values()->all(),
        'realMargin' => $rows->pluck('real_margin_with_vat')->map(fn ($v) => (float) $v)->values()->all(),
        'netResult' => $rows->pluck('net_result_with_vat')->map(fn ($v) => (float) $v)->values()->all(),
        'netResultRealized' => $rows->pluck('net_result_realized_with_vat')->map(fn ($v) => (float) $v)->values()->all(),
        'returnsLoss' => $rows->pluck('returns_loss')->map(fn ($v) => (float) $v)->values()->all(),
        'expenses' => $rows->pluck('expenses_total')->map(fn ($v) => (float) $v)->values()->all(),
        'groupLabel' => $groupLabels[$groupBy] ?? 'Período',
    ];

    $bestKey = $bestMonth->key ?? null;
    $worstKey = $worstMonth->key ?? null;
    $monthlyChart = [
        'labels' => $monthlyRows->pluck('label')->values()->all(),
        'salesWithVat' => $monthlyRows->pluck('sales_total_with_vat')->map(fn ($v) => (float) $v)->values()->all(),
        'realMargin' => $monthlyRows->pluck('real_margin_with_vat')->map(fn ($v) => (float) $v)->values()->all(),
        'netResult' => $monthlyRows->pluck('net_result_with_vat')->map(fn ($v) => (float) $v)->values()->all(),
        'netResultRealized' => $monthlyRows->pluck('net_result_realized_with_vat')->map(fn ($v) => (float) $v)->values()->all(),
        'salesCount' => $monthlyRows->pluck('sales_count')->map(fn ($v) => (int) $v)->values()->all(),
        'keys' => $monthlyRows->pluck('key')->values()->all(),
        'bestKey' => $bestKey,
        'worstKey' => $worstKey,
    ];
@endphp
<div class="topbar">
    <div>
        <h1>Contabilidad · Por período</h1>
        <p class="muted">Compara por {{ strtolower($groupLabels[$groupBy] ?? 'día') }}. <strong>Esperado</strong> = si todo se entrega · <strong>Real</strong> = solo entregadas − gastos − flete de devoluciones.</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('accounting.dashboard') }}">Resumen</a>
        <a class="btn btn-secondary" href="{{ route('costs.dashboard') }}">Resultado</a>
        <a class="btn btn-secondary" href="{{ route('costs.sellers') }}">Por vendedor</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    @include('accounting._periods')
    <form class="search" method="GET" action="{{ route('costs.periods') }}" style="flex-wrap:wrap">
        <select name="group">
            @foreach ($groupLabels as $value => $label)
                <option value="{{ $value }}" @selected($groupBy === $value)>Por {{ strtolower($label) }}</option>
            @endforeach
        </select>
        <label class="muted" for="from">Desde</label>
        <input id="from" type="date" name="from" value="{{ $from->toDateString() }}">
        <label class="muted" for="to">Hasta</label>
        <input id="to" type="date" name="to" value="{{ $to->toDateString() }}">
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

@if ($monthlyRows->isNotEmpty())
    <div class="card" style="margin-bottom:1rem">
        <div class="topbar" style="margin-bottom:.5rem">
            <div>
                <h2 style="margin:0;font-size:1.15rem">Ventas y ganancias por mes</h2>
                <p class="muted" style="margin:.35rem 0 0">
                    {{ $monthlyRows->first()?->label }} – {{ $monthlyRows->last()?->label }}.
                    Barras = ventas · líneas = margen real y resultado (después de gastos/devoluciones).
                </p>
            </div>
        </div>
        <div class="meta" style="margin:0 0 1rem">
            @if ($bestMonth)
                <div class="card">Mejor mes (ventas)
                    <strong>{{ $bestMonth->label }}</strong>
                    <div class="muted" style="margin-top:.35rem;font-size:.85rem;font-weight:500">
                        {{ money($bestMonth->sales_total_with_vat) }} · ganancia {{ money($bestMonth->real_margin_with_vat) }}
                    </div>
                </div>
            @endif
            @if ($worstMonth)
                <div class="card">Mes más bajo (ventas)
                    <strong>{{ $worstMonth->label }}</strong>
                    <div class="muted" style="margin-top:.35rem;font-size:.85rem;font-weight:500">
                        {{ money($worstMonth->sales_total_with_vat) }} · ganancia {{ money($worstMonth->real_margin_with_vat) }}
                    </div>
                </div>
            @endif
            <div class="card">Promedio ventas
                <strong>{{ money($monthlyRows->avg('sales_total_with_vat')) }}</strong>
                <div class="muted" style="margin-top:.35rem;font-size:.85rem;font-weight:500">
                    Ganancia prom. {{ money($monthlyRows->avg('real_margin_with_vat')) }}
                </div>
            </div>
            <div class="card">Promedio resultado
                <strong>{{ money($monthlyRows->avg('net_result_with_vat')) }}</strong>
                <div class="muted" style="margin-top:.35rem;font-size:.85rem;font-weight:500">
                    Tras gastos y devoluciones
                </div>
            </div>
        </div>
        <div style="position:relative;height:min(400px,60vh)">
            <canvas id="chart-monthly-sales" aria-label="Gráfico de ventas y ganancias por mes"></canvas>
        </div>
    </div>
@endif

<div class="meta">
    <div class="card"># Ventas (esperado)<strong>{{ $totals->sales_count }}</strong><span class="muted">abiertas + entregadas</span></div>
    <div class="card">Ventas c/IVA<strong>{{ money($totals->sales_total_with_vat) }}</strong></div>
    <div class="card">Margen esperado c/IVA<strong>{{ money($totals->real_margin_with_vat) }}</strong></div>
    <div class="card">Resultado esperado<strong>{{ money($totals->net_result_with_vat) }}</strong><span class="muted">si todo llega</span></div>
    <div class="card">Margen real c/IVA<strong>{{ money($totals->realized_margin_with_vat ?? 0) }}</strong><span class="muted">solo entregadas</span></div>
    <div class="card">Resultado real<strong @style(['color: var(--danger)' => ($totals->net_result_realized_with_vat ?? 0) < 0])>{{ money($totals->net_result_realized_with_vat ?? 0) }}</strong><span class="muted">tiempo real</span></div>
    <div class="card">Pendientes<strong>{{ (int) ($totals->pending_sales_count ?? 0) }}</strong><span class="muted">{{ money($totals->pending_margin_with_vat ?? 0) }} margen</span></div>
    <div class="card"># Devoluciones<strong>{{ $totals->returns_count }}</strong></div>
    <div class="card">Pérdida devoluciones<strong style="color:var(--danger)">{{ money($totals->returns_loss) }}</strong></div>
    <div class="card">Gastos<strong>{{ money($totals->expenses_total) }}</strong></div>
</div>

@if ($rows->isNotEmpty())
    <div class="card" style="margin-bottom:1rem">
        <h2 style="margin-top:0;font-size:1.1rem">Ventas vs margen vs resultado</h2>
        <p class="muted" style="margin:.25rem 0 1rem">Compara dinero por {{ strtolower($groupLabels[$groupBy] ?? 'período') }}.</p>
        <div style="position:relative;height:min(360px,55vh)">
            <canvas id="chart-money" aria-label="Gráfico de ventas y ganancias"></canvas>
        </div>
    </div>

    <div class="grid-2" style="margin-bottom:1rem">
        <div class="card">
            <h2 style="margin-top:0;font-size:1.1rem"># Ventas</h2>
            <div style="position:relative;height:min(280px,45vh)">
                <canvas id="chart-count" aria-label="Gráfico de cantidad de ventas"></canvas>
            </div>
        </div>
        <div class="card">
            <h2 style="margin-top:0;font-size:1.1rem">Gastos y pérdida por devoluciones</h2>
            <div style="position:relative;height:min(280px,45vh)">
                <canvas id="chart-costs" aria-label="Gráfico de gastos y devoluciones"></canvas>
            </div>
        </div>
    </div>
@endif

<div class="card">
    <h2 style="margin-top:0;font-size:1.1rem">Detalle por {{ strtolower($groupLabels[$groupBy] ?? 'período') }}</h2>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Período</th>
                    <th style="text-align:right"># Ventas</th>
                    <th style="text-align:right">Ventas s/IVA</th>
                    <th style="text-align:right">Ventas c/IVA</th>
                    <th style="text-align:right">COGS</th>
                    <th style="text-align:right">Margen esp.</th>
                    <th style="text-align:right">Pend.</th>
                    <th style="text-align:right">Dev.</th>
                    <th style="text-align:right">Pérdida dev.</th>
                    <th style="text-align:right">Gastos</th>
                    <th style="text-align:right">Resultado esp.</th>
                    <th style="text-align:right">Resultado real</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row->label }}</strong>
                            @if ($groupBy !== 'day')
                                <div class="muted" style="font-size:.8rem">{{ \Illuminate\Support\Carbon::parse($row->period_start)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($row->period_end)->format('d/m/Y') }}</div>
                            @endif
                        </td>
                        <td style="text-align:right">{{ $row->sales_count }}</td>
                        <td style="text-align:right">{{ money($row->sales_total) }}</td>
                        <td style="text-align:right">{{ money($row->sales_total_with_vat) }}</td>
                        <td style="text-align:right">{{ money($row->cogs_total) }}</td>
                        <td style="text-align:right">{{ money($row->real_margin_with_vat) }}</td>
                        <td style="text-align:right">{{ (int) ($row->pending_sales_count ?? 0) }}</td>
                        <td style="text-align:right">{{ $row->returns_count }}</td>
                        <td style="text-align:right">{{ money($row->returns_loss) }}</td>
                        <td style="text-align:right">{{ money($row->expenses_total) }}</td>
                        <td style="text-align:right">
                            <strong @style(['color: var(--danger)' => $row->net_result_with_vat < 0])>
                                {{ money($row->net_result_with_vat) }}
                            </strong>
                        </td>
                        <td style="text-align:right">
                            <strong @style(['color: var(--danger)' => ($row->net_result_realized_with_vat ?? 0) < 0])>
                                {{ money($row->net_result_realized_with_vat ?? 0) }}
                            </strong>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="muted">Sin datos en el período.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr>
                        <td><strong>Total</strong></td>
                        <td style="text-align:right"><strong>{{ $totals->sales_count }}</strong></td>
                        <td style="text-align:right"><strong>{{ money($totals->sales_total) }}</strong></td>
                        <td style="text-align:right"><strong>{{ money($totals->sales_total_with_vat) }}</strong></td>
                        <td style="text-align:right"><strong>{{ money($totals->cogs_total) }}</strong></td>
                        <td style="text-align:right"><strong>{{ money($totals->real_margin_with_vat) }}</strong></td>
                        <td style="text-align:right"><strong>{{ (int) ($totals->pending_sales_count ?? 0) }}</strong></td>
                        <td style="text-align:right"><strong>{{ $totals->returns_count }}</strong></td>
                        <td style="text-align:right"><strong>{{ money($totals->returns_loss) }}</strong></td>
                        <td style="text-align:right"><strong>{{ money($totals->expenses_total) }}</strong></td>
                        <td style="text-align:right"><strong>{{ money($totals->net_result_with_vat) }}</strong></td>
                        <td style="text-align:right"><strong>{{ money($totals->net_result_realized_with_vat ?? 0) }}</strong></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@if ($rows->isNotEmpty() || $monthlyRows->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(() => {
    const data = @json($chartPayload);
    const monthly = @json($monthlyChart);
    const moneyTick = (value) => '$' + Number(value).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    const moneyTip = (value) => '$' + Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: (ctx) => {
                        const label = ctx.dataset.label || '';
                        const raw = ctx.parsed.y ?? ctx.parsed;
                        if (ctx.dataset.yAxisID === 'yCount' || ctx.dataset.isCount) {
                            return `${label}: ${raw}`;
                        }
                        return `${label}: ${moneyTip(raw)}`;
                    },
                },
            },
        },
        scales: {
            x: {
                ticks: {
                    maxRotation: 45,
                    minRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: 14,
                },
            },
        },
    };

    const monthlyEl = document.getElementById('chart-monthly-sales');
    if (monthlyEl && window.Chart && monthly.labels?.length) {
        const barColors = monthly.keys.map((key) => {
            if (key === monthly.bestKey && key === monthly.worstKey) return 'rgba(17, 17, 17, 0.8)';
            if (key === monthly.bestKey) return 'rgba(15, 118, 110, 0.9)';
            if (key === monthly.worstKey) return 'rgba(185, 28, 28, 0.85)';
            return 'rgba(30, 64, 175, 0.75)';
        });

        new Chart(monthlyEl, {
            type: 'bar',
            data: {
                labels: monthly.labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Ventas c/IVA',
                        data: monthly.salesWithVat,
                        backgroundColor: barColors,
                        borderRadius: 5,
                        order: 4,
                        yAxisID: 'y',
                    },
                    {
                        type: 'line',
                        label: 'Ganancia (margen real)',
                        data: monthly.realMargin,
                        borderColor: '#0f766e',
                        backgroundColor: 'rgba(15, 118, 110, 0.12)',
                        borderWidth: 2.5,
                        tension: 0.25,
                        pointRadius: 3,
                        pointBackgroundColor: '#0f766e',
                        order: 1,
                        yAxisID: 'y',
                    },
                    {
                        type: 'line',
                        label: 'Resultado esperado',
                        data: monthly.netResult,
                        borderColor: '#e85d04',
                        backgroundColor: 'rgba(232, 93, 4, 0.12)',
                        borderWidth: 2.5,
                        tension: 0.25,
                        pointRadius: 3,
                        pointBackgroundColor: '#e85d04',
                        order: 2,
                        yAxisID: 'y',
                    },
                    {
                        type: 'line',
                        label: 'Resultado real',
                        data: monthly.netResultRealized,
                        borderColor: '#15803d',
                        backgroundColor: 'rgba(21, 128, 61, 0.12)',
                        borderWidth: 2.5,
                        tension: 0.25,
                        pointRadius: 3,
                        pointBackgroundColor: '#15803d',
                        order: 2,
                        yAxisID: 'y',
                    },
                    {
                        type: 'line',
                        label: '# Ventas',
                        data: monthly.salesCount,
                        borderColor: '#64748b',
                        backgroundColor: 'rgba(100, 116, 139, 0.12)',
                        borderWidth: 2,
                        borderDash: [5, 4],
                        tension: 0.25,
                        pointRadius: 2,
                        pointBackgroundColor: '#64748b',
                        order: 3,
                        yAxisID: 'yCount',
                        isCount: true,
                    },
                ],
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    legend: {
                        position: 'bottom',
                        labels: {
                            generateLabels(chart) {
                                const items = Chart.defaults.plugins.legend.labels.generateLabels(chart);
                                items.push({
                                    text: 'Mejor mes (ventas)',
                                    fillStyle: 'rgba(15, 118, 110, 0.9)',
                                    strokeStyle: 'rgba(15, 118, 110, 0.9)',
                                    lineWidth: 0,
                                    hidden: false,
                                    datasetIndex: -1,
                                });
                                items.push({
                                    text: 'Mes más bajo (ventas)',
                                    fillStyle: 'rgba(185, 28, 28, 0.85)',
                                    strokeStyle: 'rgba(185, 28, 28, 0.85)',
                                    lineWidth: 0,
                                    hidden: false,
                                    datasetIndex: -1,
                                });
                                return items;
                            },
                        },
                    },
                },
                scales: {
                    ...commonOptions.scales,
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        ticks: { callback: moneyTick },
                        title: { display: true, text: 'USD c/IVA' },
                    },
                    yCount: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { precision: 0 },
                        title: { display: true, text: '# ventas' },
                    },
                },
            },
        });
    }

    const moneyEl = document.getElementById('chart-money');
    if (moneyEl && window.Chart && data.labels?.length) {
        new Chart(moneyEl, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Ventas c/IVA',
                        data: data.salesWithVat,
                        backgroundColor: 'rgba(17, 17, 17, 0.75)',
                        borderRadius: 4,
                        order: 3,
                    },
                    {
                        type: 'line',
                        label: 'Margen esperado',
                        data: data.realMargin,
                        borderColor: '#0f766e',
                        backgroundColor: 'rgba(15, 118, 110, 0.15)',
                        borderWidth: 2.5,
                        tension: 0.25,
                        pointRadius: 3,
                        order: 1,
                    },
                    {
                        type: 'line',
                        label: 'Resultado esperado',
                        data: data.netResult,
                        borderColor: '#e85d04',
                        backgroundColor: 'rgba(232, 93, 4, 0.12)',
                        borderWidth: 2.5,
                        tension: 0.25,
                        pointRadius: 3,
                        order: 2,
                    },
                    {
                        type: 'line',
                        label: 'Resultado real',
                        data: data.netResultRealized,
                        borderColor: '#15803d',
                        backgroundColor: 'rgba(21, 128, 61, 0.12)',
                        borderWidth: 2.5,
                        tension: 0.25,
                        pointRadius: 3,
                        order: 2,
                    },
                ],
            },
            options: {
                ...commonOptions,
                scales: {
                    ...commonOptions.scales,
                    y: {
                        beginAtZero: true,
                        ticks: { callback: moneyTick },
                    },
                },
            },
        });
    }

    const countEl = document.getElementById('chart-count');
    if (countEl && window.Chart && data.labels?.length) {
        new Chart(countEl, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: '# Ventas',
                    data: data.salesCount,
                    backgroundColor: 'rgba(30, 64, 175, 0.8)',
                    borderRadius: 4,
                    isCount: true,
                }],
            },
            options: {
                ...commonOptions,
                scales: {
                    ...commonOptions.scales,
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                    },
                },
            },
        });
    }

    const costsEl = document.getElementById('chart-costs');
    if (costsEl && window.Chart && data.labels?.length) {
        new Chart(costsEl, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Gastos',
                        data: data.expenses,
                        backgroundColor: 'rgba(100, 116, 139, 0.85)',
                        borderRadius: 4,
                    },
                    {
                        label: 'Pérdida devoluciones',
                        data: data.returnsLoss,
                        backgroundColor: 'rgba(185, 28, 28, 0.8)',
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                ...commonOptions,
                scales: {
                    ...commonOptions.scales,
                    x: { ...commonOptions.scales.x, stacked: true },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { callback: moneyTick },
                    },
                },
            },
        });
    }
})();
</script>
@endif
@endsection
