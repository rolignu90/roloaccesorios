@extends('layouts.app')

@section('title', 'Entradas de stock')

@section('content')
<div class="topbar">
    <div>
        <h1>Entradas de stock</h1>
        <p class="muted">Historial de lotes FIFO · rectifica si hubo un error de captura</p>
    </div>
    <a class="btn" href="{{ route('inventory.stock.create') }}">Nueva entrada</a>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('inventory.stock.index') }}" style="flex-wrap:wrap">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Lote, factura o producto">
        <select name="product_id">
            <option value="">Todos los productos</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>
                    {{ $product->code }} — {{ $product->name }}
                </option>
            @endforeach
        </select>
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Lote</th>
                <th>Producto</th>
                <th>Proveedor</th>
                <th>Recibido</th>
                <th>Restante</th>
                <th>Costo</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lots as $lot)
                @php
                    $unused = (int) $lot->quantity_remaining === (int) $lot->quantity_received && (int) $lot->quantity_remaining > 0;
                    $empty = (int) $lot->quantity_remaining === 0 && (int) $lot->quantity_received > 0;
                @endphp
                <tr>
                    <td>{{ $lot->received_at?->format('d/m/Y') }}</td>
                    <td>{{ $lot->lot_number }}</td>
                    <td>
                        <a href="{{ route('inventory.products.show', $lot->product_id) }}">
                            {{ $lot->product?->code }} — {{ $lot->product?->name }}
                        </a>
                    </td>
                    <td>{{ $lot->supplier?->name ?: '—' }}</td>
                    <td>{{ $lot->quantity_received }}</td>
                    <td>
                        {{ $lot->quantity_remaining }}
                        @if ($unused)
                            <span class="badge badge-ok">Sin usar</span>
                        @elseif ($empty)
                            <span class="badge badge-off">Agotado/anulado</span>
                        @else
                            <span class="badge badge-warn">Parcial</span>
                        @endif
                    </td>
                    <td>{{ money($lot->purchase_price) }}</td>
                    <td class="actions">
                        @if ((int) $lot->quantity_remaining > 0)
                            <a class="btn btn-secondary" href="{{ route('inventory.stock.edit', $lot) }}">Rectificar</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Aún no hay entradas de stock.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $lots->links() }}</div>
</div>
@endsection
