@extends('layouts.app')

@section('title', 'Márgenes')

@section('content')
<div class="topbar">
    <div>
        <h1>Márgenes detallados</h1>
        <p class="muted">Por venta y por producto. El margen real descuenta costo de courier y comisión COD (p. ej. envío gratis).</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('costs.dashboard') }}">Dashboard</a>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('costs.margins') }}">
        <input type="date" name="from" value="{{ $from->toDateString() }}">
        <input type="date" name="to" value="{{ $to->toDateString() }}">
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="card" style="margin-bottom:1rem">
    <h2 style="margin-top:0;font-size:1.1rem">Por venta</h2>
    <table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Base s/IVA</th>
                <th>Productos c/IVA</th>
                <th>COGS</th>
                <th>Margen s/IVA</th>
                <th>Margen c/IVA</th>
                <th>Margen real s/IVA</th>
                <th>Margen real c/IVA</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($salesMargins as $sale)
                <tr>
                    <td><a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a></td>
                    <td>{{ $sale->sold_at->format('d/m/Y') }}</td>
                    <td>{{ $sale->customer?->name }}</td>
                    <td>{{ money($sale->taxable_base) }}</td>
                    <td>{{ money($sale->productsTotalWithVat()) }}</td>
                    <td>{{ money($sale->cogs_total) }}</td>
                    <td>{{ money($sale->grossMarginWithoutVat()) }}</td>
                    <td>{{ money($sale->grossMarginWithVat()) }}</td>
                    <td>{{ money($sale->realMarginWithoutVat()) }}</td>
                    <td>{{ money($sale->realMarginWithVat()) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted">Sin ventas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h2 style="margin-top:0;font-size:1.1rem">Por producto</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant. vendida</th>
                <th>Ventas s/IVA</th>
                <th>Ventas c/IVA</th>
                <th>COGS</th>
                <th>Margen prod. s/IVA</th>
                <th>Margen prod. c/IVA</th>
                <th>Envío neto</th>
                <th>Margen real s/IVA</th>
                <th>Margen real c/IVA</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($productMargins as $row)
                <tr>
                    <td>{{ $row->product?->code }} — {{ $row->product?->name }}</td>
                    <td>{{ (int) $row->qty_sold }}</td>
                    <td>{{ money($row->sales_total) }}</td>
                    <td>{{ money($row->sales_total_with_vat) }}</td>
                    <td>{{ money($row->cogs_total) }}</td>
                    <td>{{ money($row->gross_margin) }}</td>
                    <td>{{ money($row->gross_margin_with_vat) }}</td>
                    <td>{{ money($row->shipping_net) }}</td>
                    <td>{{ money($row->real_margin) }}</td>
                    <td>{{ money($row->real_margin_with_vat) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
