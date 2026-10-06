@extends('layouts.app')

@section('title', 'Resultado y márgenes')

@section('content')
@php
    $expected = $summary['expected'] ?? [];
    $realized = $summary['realized'] ?? [];
    $pending = $summary['pending'] ?? [];
@endphp
<div class="topbar">
    <div>
        <h1>Contabilidad · Resultado y márgenes</h1>
        <p class="muted">Desglose: ventas → COGS → envío → devoluciones → gastos → resultado esperado vs real.</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('accounting.dashboard', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Resumen</a>
        <a class="btn btn-secondary" href="{{ route('costs.margins') }}">Márgenes detallados</a>
        <a class="btn btn-secondary" href="{{ route('costs.periods') }}">Por período</a>
        <a class="btn btn-secondary" href="{{ route('costs.sellers') }}">Por vendedor</a>
        <a class="btn" href="{{ route('costs.expenses.create') }}">Nuevo gasto</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    @include('accounting._periods')
    <form class="search" method="GET" action="{{ route('costs.dashboard') }}">
        <input type="date" name="from" value="{{ $from->toDateString() }}">
        <input type="date" name="to" value="{{ $to->toDateString() }}">
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

{{-- 1. Lo que importa: esperado vs real --}}
<h2 style="margin:0 0 .5rem;font-size:.95rem;letter-spacing:.02em;text-transform:uppercase;color:var(--muted)">1 · Resultado del período</h2>
<p class="muted" style="margin:0 0 .75rem">
    <strong>Esperado</strong> = si todo lo confirmado/en ruta se entrega.
    <strong>Real</strong> = solo lo ya entregado (tiempo real).
    Ambos restan gastos y flete perdido en devoluciones.
</p>

<div class="grid-2" style="margin-bottom:.75rem">
    <div class="card" style="border-color:#bfdbfe;background:#f8fbff">
        <h3 style="margin:0 0 .35rem;font-size:1rem">Esperado</h3>
        <p class="muted" style="margin:0 0 .75rem;font-size:.85rem">{{ (int) ($expected['sales_count'] ?? 0) }} ventas abiertas (confirmadas + en ruta + entregadas)</p>
        <div style="font-size:1.75rem;font-weight:700;letter-spacing:-.02em">{{ money($expected['net_result_with_vat'] ?? $summary['net_result_with_vat']) }}</div>
        <p class="muted" style="margin:.35rem 0 0;font-size:.85rem">Resultado c/IVA · proyección</p>
        <div class="meta" style="margin:.85rem 0 0">
            <div class="card">Ventas c/IVA<strong>{{ money($expected['sales_total_with_vat'] ?? 0) }}</strong></div>
            <div class="card">Margen c/IVA<strong>{{ money($expected['real_margin_with_vat'] ?? 0) }}</strong><span class="muted">prod + envío neto</span></div>
        </div>
    </div>
    <div class="card" style="border-color:#a7f3d0;background:#f0fdf4">
        <h3 style="margin:0 0 .35rem;font-size:1rem">Real (cerrado)</h3>
        <p class="muted" style="margin:0 0 .75rem;font-size:.85rem">{{ (int) ($realized['sales_count'] ?? 0) }} entregadas</p>
        <div style="font-size:1.75rem;font-weight:700;letter-spacing:-.02em" @style(['color: var(--danger)' => ($realized['net_result_with_vat'] ?? 0) < 0])>
            {{ money($realized['net_result_with_vat'] ?? 0) }}
        </div>
        <p class="muted" style="margin:.35rem 0 0;font-size:.85rem">Resultado c/IVA · solo entregadas</p>
        <div class="meta" style="margin:.85rem 0 0">
            <div class="card">Ventas c/IVA<strong>{{ money($realized['sales_total_with_vat'] ?? 0) }}</strong></div>
            <div class="card">Margen c/IVA<strong>{{ money($realized['real_margin_with_vat'] ?? 0) }}</strong><span class="muted">prod + envío neto</span></div>
        </div>
    </div>
</div>

<div class="meta" style="margin-bottom:1.25rem">
    <div class="card">Aún en camino<strong>{{ (int) ($pending['sales_count'] ?? 0) }}</strong><span class="muted">confirmadas / en ruta · no entran al real</span></div>
    <div class="card">Margen pendiente c/IVA<strong>{{ money($pending['real_margin_with_vat'] ?? 0) }}</strong><span class="muted">al entregarse, el real sube hacia el esperado</span></div>
</div>

{{-- 2. Desglose único (cómo se arma el esperado) --}}
<h2 style="margin:0 0 .5rem;font-size:.95rem;letter-spacing:.02em;text-transform:uppercase;color:var(--muted)">2 · Desglose (base del esperado)</h2>
<p class="muted" style="margin:0 0 .75rem">
    Una sola vez: ventas → costo → envío → margen → restas (devoluciones + gastos) → resultado esperado.
</p>

<div class="meta">
    <div class="card">Ventas s/IVA<strong>{{ money($summary['sales_total']) }}</strong></div>
    <div class="card">IVA<strong>{{ money($summary['vat_total']) }}</strong></div>
    <div class="card">COGS FIFO<strong>{{ money($summary['cogs_total']) }}</strong></div>
    <div class="card">Margen producto c/IVA<strong>{{ money($summary['gross_margin_with_vat']) }}</strong><span class="muted">{{ number_format($summary['gross_margin_percent_with_vat'], 1) }}%</span></div>
</div>
<div class="meta" style="margin-top:.65rem">
    <div class="card">Envío cobrado<strong>{{ money($summary['shipping_charged']) }}</strong></div>
    <div class="card">Costo courier + COD<strong>{{ money($summary['carrier_cost_total']) }}</strong></div>
    <div class="card">Envío neto<strong>{{ money($summary['shipping_net']) }}</strong><span class="muted">cobrado − courier</span></div>
    <div class="card">= Margen real c/IVA<strong>{{ money($summary['real_margin_with_vat']) }}</strong><span class="muted">{{ number_format($summary['real_margin_percent_with_vat'], 1) }}%</span></div>
</div>
<div class="meta" style="margin-top:.65rem;margin-bottom:1.25rem">
    <div class="card">Devoluciones<strong>{{ (int) ($summary['returns_count'] ?? 0) }}</strong><span class="muted">{{ number_format($summary['returns_rate'] ?? 0, 1) }}% del intento</span></div>
    <div class="card">− Flete perdido (dev.)<strong style="color:var(--danger)">{{ money($summary['returns_loss'] ?? 0) }}</strong><span class="muted">sin comisión COD</span></div>
    <div class="card">− Gastos operativos<strong>{{ money($summary['expenses_total']) }}</strong></div>
    <div class="card">= Resultado esperado<strong>{{ money($summary['net_result_with_vat']) }}</strong></div>
</div>

<div class="meta" style="margin-bottom:1.25rem">
    <div class="card">Adeudado a proveedores<strong>{{ money($summary['supplier_payables_balance'] ?? 0) }}</strong><span class="muted"><a href="{{ route('inventory.payables.index') }}">Ver CxP</a> · saldo total, no del período</span></div>
</div>

{{-- 3. Detalle --}}
<h2 style="margin:0 0 .65rem;font-size:.95rem;letter-spacing:.02em;text-transform:uppercase;color:var(--muted)">3 · Detalle</h2>
<div class="grid-2" style="margin-top:.5rem">
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Devoluciones del período</h2>
        <table>
            <thead>
                <tr>
                    <th>Venta</th>
                    <th>Cliente</th>
                    <th>Courier</th>
                    <th>Flete (pérdida)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($returns as $sale)
                    <tr>
                        <td><a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a></td>
                        <td>{{ $sale->customer?->name ?? '—' }}</td>
                        <td>{{ $sale->shippingCarrier?->name ?? '—' }}</td>
                        <td><strong>{{ money($sale->carrier_shipping_cost) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Sin devoluciones en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
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
</div>

<div class="card" style="margin-top:1rem">
    <h2 style="margin-top:0;font-size:1.1rem">Margen por producto</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant.</th>
                <th>Ventas s/IVA</th>
                <th>Margen prod. c/IVA</th>
                <th>Envío neto</th>
                <th>Margen real c/IVA</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($productMargins as $row)
                <tr>
                    <td>{{ $row->product?->code }} — {{ $row->product?->name }}</td>
                    <td>{{ (int) $row->qty_sold }}</td>
                    <td>{{ money($row->sales_total) }}</td>
                    <td>{{ money($row->gross_margin_with_vat) }}</td>
                    <td>{{ money($row->shipping_net) }}</td>
                    <td>{{ money($row->real_margin_with_vat) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p style="margin-top:1rem"><a href="{{ route('costs.expenses.index') }}">Ver gastos operativos →</a></p>
</div>
@endsection
