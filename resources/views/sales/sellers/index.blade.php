@extends('layouts.app')

@section('title', 'Vendedores')

@section('content')
<div class="topbar">
    <div>
        <h1>Vendedores</h1>
        <p class="muted">Internos (salario + comisión) y externos (margen)</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('sales.seller-settlements.index') }}">Liquidaciones</a>
        <a class="btn" href="{{ route('sales.sellers.create') }}">Nuevo vendedor</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('sales.sellers.index') }}">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar código, nombre, teléfono o email">
        <button class="btn" type="submit">Buscar</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Prefijo</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Cliente vinculado</th>
                <th>Ventas</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sellers as $seller)
                <tr>
                    <td>{{ $seller->code }}</td>
                    <td><strong>{{ $seller->sale_prefix ?: 'V-' }}</strong></td>
                    <td><a href="{{ route('sales.sellers.show', $seller) }}">{{ $seller->name }}</a></td>
                    <td>{{ $seller->typeLabel() }}</td>
                    <td>
                        @if ($seller->customer)
                            <a href="{{ route('sales.customers.show', $seller->customer) }}">{{ $seller->customer->code }} — {{ $seller->customer->name }}</a>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>{{ $seller->sales_count }}</td>
                    <td>
                        <span class="badge {{ $seller->is_active ? 'badge-ok' : 'badge-off' }}">
                            {{ $seller->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('sales.sellers.edit', $seller) }}">Editar</a>
                        ·
                        <a href="{{ route('sales.seller-settlements.create', ['seller_id' => $seller->id]) }}">Liquidar</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Aún no hay vendedores. Crea uno para asignarlo en ventas.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $sellers->links() }}</div>
</div>
@endsection
