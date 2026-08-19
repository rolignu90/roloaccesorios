@extends('layouts.app')

@section('title', $combo->name)

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $combo->code }} — {{ $combo->name }}</h1>
        <p class="muted">
            Precio {{ money($combo->salePriceWithVat()) }} c/IVA
            · hasta {{ (int) $combo->available_stock }} combos con stock actual
            @if ($combo->free_shipping)
                · <span class="badge badge-ok">Envío gratis</span>
            @endif
            @unless ($combo->is_active)
                · <span class="badge badge-off">Inactivo</span>
            @endunless
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('inventory.combos.edit', $combo) }}">Editar</a>
        <a class="btn btn-secondary" href="{{ route('inventory.combos.index') }}">Volver</a>
    </div>
</div>

@if ($combo->description)
    <div class="card" style="margin-bottom:1rem">
        <p style="margin:0">{{ $combo->description }}</p>
    </div>
@endif

<div class="card">
    <h2 style="margin-top:0;font-size:1.1rem">Componentes</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant. por combo</th>
                <th>Stock actual</th>
                <th>Precio individual c/IVA</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($combo->items as $item)
                <tr>
                    <td>
                        <a href="{{ route('inventory.products.show', $item->product) }}">
                            {{ $item->product?->code }} — {{ $item->product?->name }}
                        </a>
                    </td>
                    <td>{{ (int) $item->quantity }}</td>
                    <td>{{ (int) ($item->product?->stock_on_hand ?? 0) }}</td>
                    <td>{{ $item->product ? money($item->product->effectiveSalePriceWithVat()) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($combo->is_active)
    <form method="POST" action="{{ route('inventory.combos.destroy', $combo) }}" style="margin-top:1rem" onsubmit="return confirm('¿Desactivar este combo?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-secondary" type="submit" style="color:#b91c1c">Desactivar combo</button>
    </form>
@endif
@endsection
