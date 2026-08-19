@extends('layouts.app')

@section('title', 'Entregas por mes')

@section('content')
<div class="topbar">
    <div>
        <h1>Entregas por mes</h1>
        <p class="muted">Cantidad de productos entregados en consignación por mes y consignatario</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('consignments.dashboard') }}">Dashboard</a>
        <a class="btn btn-secondary" href="{{ route('consignments.index') }}">Entregas</a>
        <a class="btn" href="{{ route('consignments.create') }}">Nueva consignación</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('consignments.monthly-products') }}" style="flex-wrap:wrap">
        <label class="muted" for="from">Desde</label>
        <input id="from" type="date" name="from" value="{{ $from->toDateString() }}">
        <label class="muted" for="to">Hasta</label>
        <input id="to" type="date" name="to" value="{{ $to->toDateString() }}">
        <select name="party_type">
            <option value="">Todos (cliente/vendedor)</option>
            <option value="customer" @selected(request('party_type') === 'customer')>Cliente</option>
            <option value="seller" @selected(request('party_type') === 'seller')>Vendedor</option>
        </select>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cliente, vendedor o producto">
        <button class="btn" type="submit">Filtrar</button>
    </form>
    <p class="muted" style="margin:.65rem 0 0">
        Suma la cantidad entregada (sin descontar devoluciones). Las consignaciones anuladas no se incluyen.
    </p>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Mes</th>
                <th>Consignatario</th>
                <th>Producto</th>
                <th style="text-align:right">Cantidad</th>
                <th style="text-align:right">Precio c/IVA</th>
                <th style="text-align:right">Total c/IVA</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td style="text-transform:capitalize">{{ $row->month_label }}</td>
                    <td>
                        {{ $row->party_name }}
                        <div class="muted" style="margin-top:.2rem;font-size:.78rem">
                            {{ $row->party_type === 'seller' ? 'Vendedor' : 'Cliente' }}
                        </div>
                    </td>
                    <td>
                        {{ $row->product_name }}
                        <div class="muted" style="margin-top:.2rem;font-size:.78rem">{{ $row->product_code }}</div>
                    </td>
                    <td style="text-align:right"><strong>{{ number_format((int) $row->quantity) }}</strong></td>
                    <td style="text-align:right">{{ money($row->unit_price_with_vat) }}</td>
                    <td style="text-align:right"><strong>{{ money($row->total_with_vat) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">No hay entregas en el período seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="3"><strong>Totales</strong></td>
                    <td style="text-align:right"><strong>{{ number_format((int) $rows->sum('quantity')) }}</strong></td>
                    <td></td>
                    <td style="text-align:right"><strong>{{ money($rows->sum('total_with_vat')) }}</strong></td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
@endsection
