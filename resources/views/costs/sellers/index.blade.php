@extends('layouts.app')

@section('title', 'Ventas por vendedor')

@section('content')
<div class="topbar">
    <div>
        <h1>Ventas por vendedor</h1>
        <p class="muted">Solo ventas confirmadas. Incluye productos, envío y margen real (courier + COD).</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('costs.dashboard') }}">Dashboard costos</a>
        <a class="btn btn-secondary" href="{{ route('costs.margins') }}">Márgenes</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('costs.sellers') }}" style="flex-wrap:wrap">
        <label class="muted" for="from">Desde</label>
        <input id="from" type="date" name="from" value="{{ $from->toDateString() }}">
        <label class="muted" for="to">Hasta</label>
        <input id="to" type="date" name="to" value="{{ $to->toDateString() }}">
        <select name="seller_id">
            <option value="">Todos los vendedores</option>
            @foreach ($sellers as $seller)
                <option value="{{ $seller->id }}" @selected((string) $sellerId === (string) $seller->id)>
                    {{ $seller->code }} — {{ $seller->name }}
                </option>
            @endforeach
        </select>
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="meta">
    <div class="card"># Ventas<strong>{{ $totals->sales_count }}</strong></div>
    <div class="card">Productos s/IVA<strong>{{ money($totals->sales_total) }}</strong></div>
    <div class="card">Productos c/IVA<strong>{{ money($totals->sales_total_with_vat) }}</strong></div>
    <div class="card">Envío cobrado<strong>{{ money($totals->shipping_charged) }}</strong></div>
    <div class="card">Costo courier+COD<strong>{{ money($totals->carrier_cost_total) }}</strong></div>
    <div class="card">Envío neto<strong>{{ money($totals->shipping_net) }}</strong></div>
    <div class="card">COGS<strong>{{ money($totals->cogs_total) }}</strong></div>
    <div class="card">Margen prod. c/IVA<strong>{{ money($totals->gross_margin_with_vat) }}</strong></div>
    <div class="card">Margen real c/IVA<strong>{{ money($totals->real_margin_with_vat) }}</strong></div>
</div>

<div class="card" style="margin-bottom:1rem">
    <h2 style="margin-top:0;font-size:1.1rem">Resumen por vendedor</h2>
    <table>
        <thead>
            <tr>
                <th>Vendedor</th>
                <th style="text-align:right"># Ventas</th>
                <th style="text-align:right">Productos s/IVA</th>
                <th style="text-align:right">Productos c/IVA</th>
                <th style="text-align:right">Envío cobrado</th>
                <th style="text-align:right">Costo courier+COD</th>
                <th style="text-align:right">Envío neto</th>
                <th style="text-align:right">COGS</th>
                <th style="text-align:right">Margen prod. c/IVA</th>
                <th style="text-align:right">Margen real c/IVA</th>
                <th style="text-align:right">% Real</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bySeller as $row)
                <tr>
                    <td>
                        <a href="{{ route('costs.sellers', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'seller_id' => $row->seller_id]) }}">
                            {{ $row->seller_code }} — {{ $row->seller_name }}
                        </a>
                    </td>
                    <td style="text-align:right">{{ $row->sales_count }}</td>
                    <td style="text-align:right">{{ money($row->sales_total) }}</td>
                    <td style="text-align:right">{{ money($row->sales_total_with_vat) }}</td>
                    <td style="text-align:right">{{ money($row->shipping_charged) }}</td>
                    <td style="text-align:right">{{ money($row->carrier_cost_total) }}</td>
                    <td style="text-align:right">{{ money($row->shipping_net) }}</td>
                    <td style="text-align:right">{{ money($row->cogs_total) }}</td>
                    <td style="text-align:right">{{ money($row->gross_margin_with_vat) }}</td>
                    <td style="text-align:right"><strong>{{ money($row->real_margin_with_vat) }}</strong></td>
                    <td style="text-align:right">{{ number_format($row->real_margin_percent_with_vat, 1) }}%</td>
                </tr>
            @empty
                <tr><td colspan="11" class="muted">Sin ventas confirmadas en el período.</td></tr>
            @endforelse
        </tbody>
        @if ($bySeller->isNotEmpty())
            <tfoot>
                <tr>
                    <td><strong>Total</strong></td>
                    <td style="text-align:right"><strong>{{ $totals->sales_count }}</strong></td>
                    <td style="text-align:right"><strong>{{ money($totals->sales_total) }}</strong></td>
                    <td style="text-align:right"><strong>{{ money($totals->sales_total_with_vat) }}</strong></td>
                    <td style="text-align:right"><strong>{{ money($totals->shipping_charged) }}</strong></td>
                    <td style="text-align:right"><strong>{{ money($totals->carrier_cost_total) }}</strong></td>
                    <td style="text-align:right"><strong>{{ money($totals->shipping_net) }}</strong></td>
                    <td style="text-align:right"><strong>{{ money($totals->cogs_total) }}</strong></td>
                    <td style="text-align:right"><strong>{{ money($totals->gross_margin_with_vat) }}</strong></td>
                    <td style="text-align:right"><strong>{{ money($totals->real_margin_with_vat) }}</strong></td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

<div class="card">
    <h2 style="margin-top:0;font-size:1.1rem">
        Detalle de ventas
        @if ($sellerId)
            @php $selected = $sellers->firstWhere('id', $sellerId); @endphp
            @if ($selected)
                · {{ $selected->code }} — {{ $selected->name }}
            @endif
        @endif
    </h2>
    <table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Fecha</th>
                <th>Vendedor</th>
                <th>Cliente</th>
                <th style="text-align:right">Productos c/IVA</th>
                <th style="text-align:right">Envío</th>
                <th style="text-align:right">Total</th>
                <th style="text-align:right">COGS</th>
                <th style="text-align:right">Margen real c/IVA</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sales as $sale)
                <tr>
                    <td><a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a></td>
                    <td>{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $sale->seller?->code }} — {{ $sale->seller?->name ?? '—' }}</td>
                    <td>{{ $sale->customer?->name }}</td>
                    <td style="text-align:right">{{ money($sale->productsTotalWithVat()) }}</td>
                    <td style="text-align:right">{{ money($sale->shipping_amount) }}</td>
                    <td style="text-align:right"><strong>{{ money($sale->total) }}</strong></td>
                    <td style="text-align:right">{{ money($sale->cogs_total) }}</td>
                    <td style="text-align:right">{{ money($sale->realMarginWithVat()) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Sin ventas en el período.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
