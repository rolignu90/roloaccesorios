@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
<div class="topbar">
    <div>
        <h1>Clientes</h1>
        <p class="muted">Clientes para el módulo de ventas</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('sales.customers.index', ['returns' => 1]) }}">
            Con devoluciones ({{ (int) $returnsCustomersCount }})
        </a>
        <a class="btn" href="{{ route('sales.customers.create') }}">Nuevo cliente</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('sales.customers.index') }}" style="flex-wrap:wrap">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar código, nombre, documento o teléfono">
        <select name="returns">
            <option value="">Todos</option>
            <option value="1" @selected($onlyReturns)>Solo con devoluciones</option>
        </select>
        <button class="btn" type="submit">Buscar</button>
        @if ($onlyReturns || request('q'))
            <a class="btn btn-secondary" href="{{ route('sales.customers.index') }}">Limpiar</a>
        @endif
    </form>
</div>

@if ($onlyReturns)
    <div class="flash" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-bottom:1rem">
        Mostrando clientes con al menos una venta en devolución.
    </div>
@endif

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Documento</th>
                <th>Teléfono</th>
                <th>Devoluciones</th>
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
                        @if ((int) $customer->returns_count > 0)
                            <span class="badge badge-off">{{ (int) $customer->returns_count }}</span>
                            @if ($customer->last_return_at)
                                <div class="muted" style="font-size:.8rem">Última {{ \Illuminate\Support\Carbon::parse($customer->last_return_at)->format('d/m/Y') }}</div>
                            @endif
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $customer->is_active ? 'badge-ok' : 'badge-off' }}">
                            {{ $customer->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="actions">
                        <a href="{{ route('sales.customers.show', $customer) }}">Ver</a>
                        <a href="{{ route('sales.customers.edit', $customer) }}">Editar</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">{{ $onlyReturns ? 'No hay clientes con devoluciones.' : 'Aún no hay clientes.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $customers->links() }}</div>
</div>
@endsection
