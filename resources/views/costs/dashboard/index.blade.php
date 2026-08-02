@extends('layouts.app')

@section('title', 'Costos')

@section('content')
<div class="topbar">
    <div>
        <h1>Costos y márgenes</h1>
        <p class="muted">COGS FIFO + gastos operativos (USD). Márgenes de producto (sin incluir envío).</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('costs.margins') }}">Márgenes detallados</a>
        <a class="btn" href="{{ route('costs.expenses.create') }}">Nuevo gasto</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('costs.dashboard') }}">
        <input type="date" name="from" value="{{ $from->toDateString() }}">
        <input type="date" name="to" value="{{ $to->toDateString() }}">
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="meta">
    <div class="card">Ventas s/IVA<strong>{{ money($summary['sales_total']) }}</strong></div>
    <div class="card">Ventas c/IVA<strong>{{ money($summary['sales_total_with_vat']) }}</strong></div>
    <div class="card">COGS FIFO<strong>{{ money($summary['cogs_total']) }}</strong></div>
    <div class="card">Margen s/IVA<strong>{{ money($summary['gross_margin_without_vat']) }}</strong><span class="muted">{{ number_format($summary['gross_margin_percent'], 1) }}%</span></div>
    <div class="card">Margen c/IVA<strong>{{ money($summary['gross_margin_with_vat']) }}</strong><span class="muted">{{ number_format($summary['gross_margin_percent_with_vat'], 1) }}%</span></div>
    <div class="card">Gastos operativos<strong>{{ money($summary['expenses_total']) }}</strong></div>
    <div class="card">Resultado s/IVA<strong>{{ money($summary['net_result']) }}</strong></div>
    <div class="card">Resultado c/IVA<strong>{{ money($summary['net_result_with_vat']) }}</strong></div>
    <div class="card"># Ventas<strong>{{ $summary['sales_count'] }}</strong></div>
</div>

<div class="grid-2">
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Últimas ventas del período</h2>
        <table>
            <thead>
                <tr>
                    <th>Venta</th>
                    <th>Base s/IVA</th>
                    <th>COGS</th>
                    <th>Margen s/IVA</th>
                    <th>Margen c/IVA</th>
                    <th>Margen real c/IVA</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($salesMargins as $sale)
                    <tr>
                        <td><a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a></td>
                        <td>{{ money($sale->taxable_base) }}</td>
                        <td>{{ money($sale->cogs_total) }}</td>
                        <td>{{ money($sale->grossMarginWithoutVat()) }}</td>
                        <td>{{ money($sale->grossMarginWithVat()) }}</td>
                        <td>{{ money($sale->realMarginWithVat()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Sin ventas en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Margen por producto</h2>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cant.</th>
                    <th>Ventas s/IVA</th>
                    <th>Margen s/IVA</th>
                    <th>Margen c/IVA</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($productMargins as $row)
                    <tr>
                        <td>{{ $row->product?->code }} — {{ $row->product?->name }}</td>
                        <td>{{ (int) $row->qty_sold }}</td>
                        <td>{{ money($row->sales_total) }}</td>
                        <td>{{ money($row->gross_margin) }}</td>
                        <td>{{ money($row->gross_margin_with_vat) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Sin datos.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p style="margin-top:1rem"><a href="{{ route('costs.expenses.index') }}">Ver gastos operativos →</a></p>
    </div>
</div>
@endsection
