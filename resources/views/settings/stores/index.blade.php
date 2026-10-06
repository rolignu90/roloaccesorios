@extends('layouts.app')

@section('title', 'Tiendas')

@section('content')
<div class="topbar">
    <div>
        <h1>Tiendas</h1>
        <p class="muted">Datos del ticket, vendedor por defecto y prefijo de cada tienda física. El inventario es compartido.</p>
    </div>
    <a class="btn" href="{{ route('settings.stores.create') }}">Nueva tienda</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Tienda</th>
                <th>Dirección / teléfono</th>
                <th>Vendedor (prefijo)</th>
                <th style="text-align:right">Ventas</th>
                <th>Caja</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($stores as $store)
                <tr>
                    <td><strong>{{ $store->name }}</strong>@if ($store->tax_id)<div class="muted" style="font-size:.8rem">NIT/NRC {{ $store->tax_id }}</div>@endif</td>
                    <td>{{ $store->address ?: '—' }}<div class="muted" style="font-size:.8rem">{{ $store->phone }}</div></td>
                    <td>{{ $store->seller?->name ?? '—' }} @if ($store->seller)<span class="muted">({{ $store->seller->sale_prefix }})</span>@endif</td>
                    <td style="text-align:right">{{ $store->store_sales_count }}</td>
                    <td>
                        <span class="badge {{ in_array($store->id, $openStoreIds, true) ? 'badge-ok' : 'badge-off' }}">
                            {{ in_array($store->id, $openStoreIds, true) ? 'Abierta' : 'Cerrada' }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $store->is_active ? 'badge-ok' : 'badge-off' }}">{{ $store->is_active ? 'Activa' : 'Inactiva' }}</span>
                    </td>
                    <td class="actions"><a href="{{ route('settings.stores.edit', $store) }}">Editar</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Aún no hay tiendas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
