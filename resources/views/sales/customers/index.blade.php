@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
<div class="topbar">
    <div>
        <h1>Clientes</h1>
        <p class="muted">Clientes para el módulo de ventas</p>
    </div>
    <a class="btn" href="{{ route('sales.customers.create') }}">Nuevo cliente</a>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('sales.customers.index') }}">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar código, nombre o documento">
        <button class="btn" type="submit">Buscar</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Documento</th>
                <th>Teléfono</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($customers as $customer)
                <tr>
                    <td>{{ $customer->code }}</td>
                    <td><a href="{{ route('sales.customers.show', $customer) }}">{{ $customer->name }}</a></td>
                    <td>
                        {{ $customer->document_type }}
                        @if ($customer->document_type !== 'N/A' && $customer->document_number)
                            {{ $customer->document_number }}
                        @endif
                    </td>
                    <td>{{ $customer->phone ?: '—' }}</td>
                    <td>
                        <span class="badge {{ $customer->is_active ? 'badge-ok' : 'badge-off' }}">
                            {{ $customer->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="actions">
                        <a href="{{ route('sales.customers.show', $customer) }}">Precios</a>
                        <a href="{{ route('sales.customers.edit', $customer) }}">Editar</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Aún no hay clientes.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $customers->links() }}</div>
</div>
@endsection
