@extends('layouts.app')

@section('title', 'Proveedores')

@section('content')
<div class="topbar">
    <div>
        <h1>Proveedores</h1>
        <p class="muted">Catálogo de proveedores del inventario</p>
    </div>
    <a class="btn" href="{{ route('inventory.suppliers.create') }}">Nuevo proveedor</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Contacto</th>
                <th>Teléfono</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($suppliers as $supplier)
                <tr>
                    <td>{{ $supplier->code }}</td>
                    <td><a href="{{ route('inventory.suppliers.show', $supplier) }}">{{ $supplier->name }}</a></td>
                    <td>{{ $supplier->contact_name ?: '—' }}</td>
                    <td>{{ $supplier->phone ?: '—' }}</td>
                    <td>
                        <span class="badge {{ $supplier->is_active ? 'badge-ok' : 'badge-off' }}">
                            {{ $supplier->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="actions">
                        <a href="{{ route('inventory.suppliers.edit', $supplier) }}">Editar</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">Aún no hay proveedores.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:1rem">{{ $suppliers->links() }}</div>
</div>
@endsection
