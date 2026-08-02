@extends('layouts.app')

@section('title', 'Historial de movimientos')

@section('content')
<div class="topbar">
    <div>
        <h1>Historial de movimientos</h1>
        <p class="muted">Entradas, ventas, anulaciones y ajustes de inventario</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('inventory.stock.index') }}">Ver entradas</a>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('inventory.movements.index') }}" style="flex-wrap:wrap">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Producto, lote o nota">
        <select name="type">
            <option value="">Todos los tipos</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="product_id">
            <option value="">Todos los productos</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>
                    {{ $product->code }} — {{ $product->name }}
                </option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}">
        <input type="date" name="to" value="{{ request('to') }}">
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Producto</th>
                <th>Lote</th>
                <th>Cantidad</th>
                <th>Costo unit.</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movements as $movement)
                <tr>
                    <td>{{ $movement->occurred_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        <span class="badge {{ $movement->isInbound() ? 'badge-ok' : 'badge-warn' }}">
                            {{ $movement->typeLabel() }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('inventory.products.show', $movement->product_id) }}">
                            {{ $movement->product?->code }} — {{ $movement->product?->name }}
                        </a>
                    </td>
                    <td>{{ $movement->inventoryLot?->lot_number ?: '—' }}</td>
                    <td>
                        <strong style="color:{{ $movement->quantity >= 0 ? 'var(--ok)' : 'var(--danger)' }}">
                            {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                        </strong>
                    </td>
                    <td>{{ $movement->unit_cost !== null ? money($movement->unit_cost) : '—' }}</td>
                    <td class="muted">{{ $movement->notes ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Sin movimientos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $movements->links() }}</div>
</div>
@endsection
