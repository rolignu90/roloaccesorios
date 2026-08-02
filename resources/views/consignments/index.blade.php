@extends('layouts.app')

@section('title', 'Consignaciones')

@section('content')
<div class="topbar">
    <div>
        <h1>Consignaciones</h1>
        <p class="muted">Productos entregados sin pago inmediato</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('consignments.dashboard') }}">Dashboard</a>
        <a class="btn" href="{{ route('consignments.create') }}">Nueva consignación</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('consignments.index') }}" style="flex-wrap:wrap">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Número o consignatario">
        <select name="party_type">
            <option value="">Todos (vendedor/cliente)</option>
            <option value="seller" @selected(request('party_type') === 'seller')>Vendedor</option>
            <option value="customer" @selected(request('party_type') === 'customer')>Cliente</option>
        </select>
        <select name="status">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\Consignment::STATUSES as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Fecha</th>
                <th>Consignatario</th>
                <th>Total</th>
                <th>Pagado</th>
                <th>Devuelto</th>
                <th>Saldo</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($consignments as $row)
                <tr>
                    <td><a href="{{ route('consignments.show', $row) }}">{{ $row->number }}</a></td>
                    <td>{{ $row->delivered_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $row->partyLabel() }}</td>
                    <td>{{ money($row->total_with_vat) }}</td>
                    <td>{{ money($row->paid_with_vat) }}</td>
                    <td>{{ money($row->returned_with_vat) }}</td>
                    <td><strong>{{ money($row->balance_with_vat) }}</strong></td>
                    <td>
                        <span class="badge {{ $row->isVoided() ? 'badge-off' : ($row->status === 'settled' ? 'badge-ok' : 'badge-warn') }}">
                            {{ $row->statusLabel() }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Aún no hay consignaciones.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $consignments->links() }}</div>
</div>
@endsection
