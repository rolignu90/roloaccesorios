@extends('layouts.app')

@section('title', 'Combos')

@section('content')
<div class="topbar">
    <div>
        <h1>Combos</h1>
        <p class="muted">Varios productos · un precio · descuenta stock de cada ítem al vender</p>
    </div>
    <a class="btn" href="{{ route('inventory.combos.create') }}">Nuevo combo</a>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('inventory.combos.index') }}">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por código o nombre">
        <label style="display:flex;align-items:center;gap:.4rem;white-space:nowrap">
            <input type="checkbox" name="show_inactive" value="1" @checked(request()->boolean('show_inactive'))>
            Ver inactivos
        </label>
        <button class="btn" type="submit">Buscar</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Productos</th>
                <th>Precio c/IVA</th>
                <th>Envío</th>
                <th>Stock combos</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($combos as $combo)
                <tr>
                    <td>{{ $combo->code }}</td>
                    <td><a href="{{ route('inventory.combos.show', $combo) }}">{{ $combo->name }}</a></td>
                    <td>
                        @foreach ($combo->items as $item)
                            <div class="muted" style="font-size:.9rem">
                                {{ (int) $item->quantity }}× {{ $item->product?->code }} — {{ $item->product?->name }}
                            </div>
                        @endforeach
                    </td>
                    <td>{{ money($combo->salePriceWithVat()) }}</td>
                    <td>
                        @if ($combo->free_shipping)
                            <span class="badge badge-ok">Envío gratis</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>{{ (int) $combo->available_stock }}</td>
                    <td>
                        <span class="badge {{ $combo->is_active ? 'badge-ok' : 'badge-off' }}">
                            {{ $combo->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="actions">
                        <a href="{{ route('inventory.combos.edit', $combo) }}">Editar</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Sin combos aún.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $combos->links() }}</div>
</div>
@endsection
