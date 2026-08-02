@extends('layouts.app')

@section('title', 'Dashboard consignación')

@section('content')
<div class="topbar">
    <div>
        <h1>Dashboard consignación</h1>
        <p class="muted">Entregado, adeudado y pagado (USD c/IVA)</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('consignments.index') }}">Listado</a>
        <a class="btn" href="{{ route('consignments.create') }}">Nueva consignación</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('consignments.dashboard') }}">
        <input type="date" name="from" value="{{ $from->toDateString() }}">
        <input type="date" name="to" value="{{ $to->toDateString() }}">
        <button class="btn" type="submit">Filtrar período</button>
    </form>
    <p class="muted" style="margin:.5rem 0 0">El saldo adeudado global incluye todas las consignaciones abiertas (no solo el período).</p>
</div>

<div class="meta" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
    <div class="card">Entregado (período)<strong>{{ money($delivered) }}</strong></div>
    <div class="card">Devuelto (período)<strong>{{ money($returned) }}</strong></div>
    <div class="card">Pagado (período)<strong>{{ money($paid) }}</strong></div>
    <div class="card">Saldo adeudado<strong style="color:var(--danger)">{{ money($balance) }}</strong></div>
    <div class="card">Abiertas / parciales<strong>{{ $open_count }}</strong></div>
    <div class="card">Liquidadas (período)<strong>{{ $settled_count }}</strong></div>
</div>

<div class="grid-2">
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Por consignatario</h2>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Nombre</th>
                    <th>Entregado</th>
                    <th>Pagado</th>
                    <th>Adeudado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($by_party as $row)
                    <tr>
                        <td>{{ $row->party_label }}</td>
                        <td>{{ $row->code }} — {{ $row->name }}</td>
                        <td>{{ money($row->delivered) }}</td>
                        <td>{{ money($row->paid) }}</td>
                        <td><strong>{{ money($row->balance) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Sin datos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Productos en consignación</h2>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cant. fuera</th>
                    <th>Valor c/IVA</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($top_products as $row)
                    <tr>
                        <td>{{ $row->code }} — {{ $row->name }}</td>
                        <td>{{ number_format((int) $row->qty_out) }}</td>
                        <td>{{ money($row->value_out) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">Nada pendiente fuera.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2" style="margin-top:1rem">
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Antigüedad (abiertas)</h2>
        <table>
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Consignatario</th>
                    <th>Días</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($aging as $row)
                    <tr>
                        <td><a href="{{ route('consignments.show', $row) }}">{{ $row->number }}</a></td>
                        <td>{{ $row->partyName() }}</td>
                        <td>{{ (int) $row->days_open }}</td>
                        <td>{{ money($row->balance_with_vat) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Sin saldos abiertos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Recientes</h2>
        <table>
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $row)
                    <tr>
                        <td><a href="{{ route('consignments.show', $row) }}">{{ $row->number }}</a></td>
                        <td>{{ $row->delivered_at?->format('d/m/Y') }}</td>
                        <td>{{ $row->statusLabel() }}</td>
                        <td>{{ money($row->balance_with_vat) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Aún no hay consignaciones.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
