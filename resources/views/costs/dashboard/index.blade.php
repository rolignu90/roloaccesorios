@extends('layouts.app')

@section('title', 'Costos')

@section('content')
<div class="topbar">
    <div>
        <h1>Costos y márgenes</h1>
        <p class="muted">COGS FIFO + envíos + devoluciones + gastos (USD). Una devolución cuenta como pérdida del flete del método de envío (sin comisión COD).</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('costs.margins') }}">Márgenes detallados</a>
        <a class="btn btn-secondary" href="{{ route('costs.sellers') }}">Ventas por vendedor</a>
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

{{-- 1. Ventas --}}
<h2 style="margin:0 0 .65rem;font-size:.95rem;letter-spacing:.02em;text-transform:uppercase;color:var(--muted)">1 · Ventas del período</h2>
<div class="meta">
    <div class="card"># Ventas<strong>{{ $summary['sales_count'] }}</strong><span class="muted">confirmadas / en ruta / entregadas</span></div>
    <div class="card">Ventas s/IVA<strong>{{ money($summary['sales_total']) }}</strong></div>
    <div class="card">Ventas c/IVA<strong>{{ money($summary['sales_total_with_vat']) }}</strong></div>
    <div class="card">COGS FIFO<strong>{{ money($summary['cogs_total']) }}</strong></div>
    <div class="card">Margen prod. s/IVA<strong>{{ money($summary['gross_margin_without_vat']) }}</strong><span class="muted">{{ number_format($summary['gross_margin_percent'], 1) }}%</span></div>
    <div class="card">Margen prod. c/IVA<strong>{{ money($summary['gross_margin_with_vat']) }}</strong><span class="muted">{{ number_format($summary['gross_margin_percent_with_vat'], 1) }}%</span></div>
</div>

{{-- 2. Envíos (solo ventas revenue) --}}
<h2 style="margin:1.25rem 0 .65rem;font-size:.95rem;letter-spacing:.02em;text-transform:uppercase;color:var(--muted)">2 · Envíos (ventas activas)</h2>
<div class="meta">
    <div class="card">Envío cobrado<strong>{{ money($summary['shipping_charged']) }}</strong><span class="muted">lo que pagó el cliente</span></div>
    <div class="card">Costo courier+COD<strong>{{ money($summary['carrier_cost_total']) }}</strong><span class="muted">método de envío</span></div>
    <div class="card">Envío neto<strong>{{ money($summary['shipping_net']) }}</strong><span class="muted">cobrado − courier</span></div>
    <div class="card">Margen real c/IVA<strong>{{ money($summary['real_margin_with_vat']) }}</strong><span class="muted">{{ number_format($summary['real_margin_percent_with_vat'], 1) }}% · prod + envío neto</span></div>
</div>

{{-- 3. Devoluciones --}}
<h2 style="margin:1.25rem 0 .65rem;font-size:.95rem;letter-spacing:.02em;text-transform:uppercase;color:var(--muted)">3 · Devoluciones (pérdida de envío)</h2>
<div class="meta">
    <div class="card"># Devoluciones<strong>{{ (int) ($summary['returns_count'] ?? 0) }}</strong><span class="muted">{{ number_format($summary['returns_rate'] ?? 0, 1) }}% del intento</span></div>
    <div class="card">Flete perdido<strong style="color:var(--danger)">{{ money($summary['returns_shipping_cost'] ?? 0) }}</strong><span class="muted">costo método de envío</span></div>
    <div class="card">Pérdida por devoluciones<strong style="color:var(--danger)">{{ money($summary['returns_loss'] ?? 0) }}</strong><span class="muted">solo flete · sin COD</span></div>
</div>

{{-- 4. Resultado --}}
<h2 style="margin:1.25rem 0 .65rem;font-size:.95rem;letter-spacing:.02em;text-transform:uppercase;color:var(--muted)">4 · Resultado</h2>
<div class="meta">
    <div class="card">Gastos operativos<strong>{{ money($summary['expenses_total']) }}</strong></div>
    <div class="card">Resultado s/IVA<strong>{{ money($summary['net_result']) }}</strong><span class="muted">margen − gastos − devoluciones</span></div>
    <div class="card">Resultado c/IVA<strong>{{ money($summary['net_result_with_vat']) }}</strong><span class="muted">incluye pérdida por devoluciones</span></div>
    <div class="card">Adeudado a proveedores<strong>{{ money($summary['supplier_payables_balance'] ?? 0) }}</strong><span class="muted"><a href="{{ route('inventory.payables.index') }}">Ver CxP</a></span></div>
</div>

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
