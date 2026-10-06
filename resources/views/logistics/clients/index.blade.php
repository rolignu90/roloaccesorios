@extends('layouts.app')

@section('title', 'Clientes logísticos')

@section('content')
<div class="topbar">
    <div>
        <h1>Clientes logísticos</h1>
        <p class="muted">Empresas que usan tu cuenta Sistrack · comisión por envío</p>
    </div>
    <div class="actions">
        @can('logistics.settlements')
            <a class="btn btn-secondary" href="{{ route('logistics.settlements.index') }}">Liquidaciones</a>
        @endcan
        @can('logistics.clients')
            <a class="btn" href="{{ route('logistics.clients.create') }}">Nueva empresa</a>
        @endcan
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('logistics.clients.index') }}">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar código, nombre, teléfono o email">
        <button class="btn" type="submit">Buscar</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Comisión</th>
                <th>Courier</th>
                <th>Envíos</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clients as $client)
                <tr>
                    <td>{{ $client->code }}</td>
                    <td><a href="{{ route('logistics.clients.show', $client) }}">{{ $client->name }}</a></td>
                    <td>{{ $client->commissionSummary() }}</td>
                    <td>{{ $client->defaultShippingCarrier?->name ?? '—' }}</td>
                    <td>{{ $client->shipments_count }}</td>
                    <td>
                        <span class="badge {{ $client->is_active ? 'badge-ok' : 'badge-off' }}">
                            {{ $client->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="actions">
                        @can('logistics.create')
                            <a href="{{ route('logistics.shipments.create', ['client_id' => $client->id]) }}">Nuevo envío</a>
                        @endcan
                        @can('logistics.clients')
                            <a href="{{ route('logistics.clients.edit', $client) }}">Editar</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Sin clientes logísticos aún.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $clients->links() }}
</div>
@endsection
