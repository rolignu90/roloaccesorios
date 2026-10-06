@extends('layouts.app')

@section('title', 'Contabilidad')

@section('content')
<div class="topbar">
    <div>
        <h1>Contabilidad · Resumen</h1>
        <p class="muted">Balances por estado de venta + resultado esperado vs real. El detalle de márgenes y gastos está en las otras pantallas del mismo módulo.</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('accounting.statements', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Estados de cuenta</a>
        <a class="btn btn-secondary" href="{{ route('costs.dashboard', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Resultado y márgenes</a>
        <a class="btn" href="{{ route('costs.expenses.create') }}">Nuevo gasto</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    @include('accounting._periods')
    <form class="search" method="GET" action="{{ route('accounting.dashboard') }}">
        <input type="date" name="from" value="{{ $from->toDateString() }}">
        <input type="date" name="to" value="{{ $to->toDateString() }}">
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<h2 style="margin:0 0 .65rem;font-size:.95rem;letter-spacing:.02em;text-transform:uppercase;color:var(--muted)">Balances del período</h2>
<div class="meta">
    <div class="card">Actividad<strong>{{ (int) $balances['activity_count'] }}</strong><span class="muted">{{ money($balances['activity_total_with_vat']) }} · sin anuladas</span></div>
    <div class="card" style="border-color:#fde68a;background:#fffbeb">En proceso<strong>{{ (int) $balances['open']['count'] }}</strong><span class="muted">{{ money($balances['open']['total_with_vat']) }} · confirmadas + en ruta</span></div>
    <div class="card" style="border-color:#a7f3d0;background:#f0fdf4">Entregado<strong>{{ (int) $balances['delivered']['count'] }}</strong><span class="muted">{{ money($balances['delivered']['total_with_vat']) }} · cerrado</span></div>
    <div class="card" style="border-color:#fecaca;background:#fef2f2">Devoluciones<strong>{{ (int) $balances['returned']['count'] }}</strong><span class="muted">{{ money($balances['returned']['total_with_vat']) }} · pérdida flete {{ money($balances['returns_loss']) }}</span></div>
</div>

<div class="meta" style="margin-top:.75rem;margin-bottom:1.25rem">
    <div class="card">Resultado esperado<strong>{{ money($balances['expected_net_with_vat']) }}</strong><span class="muted">si todo llega · <a href="{{ route('costs.dashboard', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">desglose</a></span></div>
    <div class="card">Resultado real<strong @style(['color: var(--danger)' => $balances['realized_net_with_vat'] < 0])>{{ money($balances['realized_net_with_vat']) }}</strong><span class="muted">solo entregadas − gastos − flete</span></div>
    <div class="card">Margen pendiente<strong>{{ money($balances['pending_margin_with_vat']) }}</strong><span class="muted">aún en camino</span></div>
    <div class="card">Gastos del período<strong>{{ money($balances['expenses_total']) }}</strong></div>
</div>

<h2 style="margin:0 0 .65rem;font-size:.95rem;letter-spacing:.02em;text-transform:uppercase;color:var(--muted)">Detalle por estado</h2>
<div class="card" style="margin-bottom:1.25rem;overflow-x:auto">
    <table>
        <thead>
            <tr>
                <th>Estado</th>
                <th style="text-align:right">#</th>
                <th style="text-align:right">Total c/IVA</th>
                <th style="text-align:right">Productos c/IVA</th>
                <th style="text-align:right">Envío cobrado</th>
                <th style="text-align:right">COGS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($balances['by_status'] as $row)
                <tr>
                    <td><strong>{{ $row['label'] }}</strong></td>
                    <td style="text-align:right">{{ $row['count'] }}</td>
                    <td style="text-align:right">{{ money($row['total_with_vat']) }}</td>
                    <td style="text-align:right">{{ money($row['products_with_vat']) }}</td>
                    <td style="text-align:right">{{ money($row['shipping_charged']) }}</td>
                    <td style="text-align:right">{{ money($row['cogs_total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="grid-2">
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Por cliente (top)</h2>
        <p class="muted" style="margin:.25rem 0 .75rem">Abierto = confirmada/en ruta. Clic para estado de cuenta.</p>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th style="text-align:right">Abierto</th>
                        <th style="text-align:right">Entregado</th>
                        <th style="text-align:right">Actividad</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($byCustomer as $row)
                        <tr>
                            <td>
                                <a href="{{ route('accounting.statements.customer', ['customer' => $row->party_id, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">
                                    {{ $row->code }} — {{ $row->name }}
                                </a>
                                @if ($row->open_count > 0)
                                    <div class="muted" style="font-size:.8rem">{{ $row->open_count }} en proceso</div>
                                @endif
                            </td>
                            <td style="text-align:right">{{ money($row->open_total) }}</td>
                            <td style="text-align:right">{{ money($row->delivered_total) }}</td>
                            <td style="text-align:right"><strong>{{ money($row->activity_total) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Sin ventas en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Por vendedor</h2>
        <p class="muted" style="margin:.25rem 0 .75rem">Misma lectura: abierto vs entregado.</p>
        <div style="overflow-x:auto">
            <table>
                <thead>
                    <tr>
                        <th>Vendedor</th>
                        <th style="text-align:right">Abierto</th>
                        <th style="text-align:right">Entregado</th>
                        <th style="text-align:right">Actividad</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bySeller as $row)
                        <tr>
                            <td>
                                <a href="{{ route('accounting.statements.seller', ['seller' => $row->party_id, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">
                                    {{ $row->code }} — {{ $row->name }}
                                </a>
                            </td>
                            <td style="text-align:right">{{ money($row->open_total) }}</td>
                            <td style="text-align:right">{{ money($row->delivered_total) }}</td>
                            <td style="text-align:right"><strong>{{ money($row->activity_total) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Sin ventas en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
