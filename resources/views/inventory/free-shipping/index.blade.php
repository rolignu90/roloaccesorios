@extends('layouts.app')

@section('title', 'Envío gratis')

@section('content')
<div class="topbar">
    <div>
        <h1>Envío gratis</h1>
        <p class="muted">Marca qué productos se envían sin costo de envío</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="topbar" style="margin-bottom:1rem">
            <strong>Con envío gratis ({{ $freeShippingProducts->count() }})</strong>
        </div>

        @forelse ($freeShippingProducts as $product)
            <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;padding:.75rem 0;border-bottom:1px solid var(--line)">
                <div>
                    <div class="muted" style="font-size:.75rem;letter-spacing:.06em;text-transform:uppercase">{{ $product->code }}</div>
                    <a href="{{ route('inventory.products.show', $product) }}"><strong>{{ $product->name }}</strong></a>
                    <div class="muted">{{ money($product->effectiveSalePriceWithVat()) }} c/IVA</div>
                </div>
                <form method="POST" action="{{ route('inventory.free-shipping.destroy', $product) }}" onsubmit="return confirm('¿Quitar envío gratis a este producto?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-secondary" type="submit">Quitar</button>
                </form>
            </div>
        @empty
            <p class="muted">Aún no hay productos con envío gratis.</p>
        @endforelse
    </div>

    <div class="card">
        <div class="topbar" style="margin-bottom:1rem">
            <strong>Agregar productos</strong>
        </div>

        <form class="search" method="GET" action="{{ route('inventory.free-shipping.index') }}" style="margin-bottom:1rem">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar producto activo…">
            <button class="btn btn-secondary" type="submit">Buscar</button>
        </form>

        <form method="POST" action="{{ route('inventory.free-shipping.store') }}">
            @csrf
            @if ($availableProducts->isEmpty())
                <p class="muted">No hay productos activos disponibles para agregar.</p>
            @else
                <div style="max-height:420px;overflow:auto;margin-bottom:1rem">
                    @foreach ($availableProducts as $product)
                        <label style="display:flex;gap:.65rem;align-items:flex-start;padding:.55rem 0;border-bottom:1px solid var(--line);cursor:pointer">
                            <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" style="margin-top:.25rem">
                            <span>
                                <span class="muted" style="display:block;font-size:.75rem;letter-spacing:.06em;text-transform:uppercase">{{ $product->code }}</span>
                                <strong>{{ $product->name }}</strong>
                                <span class="muted" style="display:block">{{ money($product->effectiveSalePriceWithVat()) }} c/IVA</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <button class="btn" type="submit">Agregar a envío gratis</button>
            @endif
        </form>
    </div>
</div>
@endsection
