@extends('layouts.app')

@section('title', 'Productos')

@section('content')
@php
    $lowStockProducts = $products->getCollection()->filter(
        fn ($product) => $product->is_active && $product->isBelowMinStock((int) ($product->stock_on_hand ?? 0))
    );
@endphp

<div class="topbar">
    <div>
        <h1>Productos</h1>
        <p class="muted">Árbol con proveedores · precios c/IVA · alertas de stock</p>
    </div>
    <div class="actions">
        <form id="labels-form" method="GET" action="{{ route('inventory.products.labels') }}" target="_blank" style="display:contents">
            <button class="btn btn-secondary" type="submit" id="labels-btn" disabled>Imprimir etiquetas (<span id="labels-count">0</span>)</button>
        </form>
        <a class="btn btn-secondary" href="{{ route('inventory.products.export', request()->only(['q', 'show_inactive'])) }}">Exportar precios</a>
        <a class="btn btn-secondary" href="{{ route('inventory.products.bulk-prices') }}">Precios masivos</a>
        <a class="btn btn-secondary" href="{{ route('inventory.stock.create') }}">Entrada de stock</a>
        <a class="btn" href="{{ route('inventory.products.create') }}">Nuevo producto</a>
    </div>
</div>

@if ($lowStockProducts->isNotEmpty())
    <div class="errors" style="margin-bottom:1rem">
        <strong>Alerta de stock mínimo ({{ $lowStockProducts->count() }}):</strong>
        <ul>
            @foreach ($lowStockProducts as $low)
                <li>
                    <a href="{{ route('inventory.products.show', $low) }}">{{ $low->code }} — {{ $low->name }}</a>
                    · stock {{ (int) ($low->stock_on_hand ?? 0) }} / mín. {{ $low->min_stock }} {{ $low->unit }}
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('inventory.products.index') }}">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por código o nombre">
        <label style="display:flex;align-items:center;gap:.4rem;white-space:nowrap">
            <input type="checkbox" name="show_inactive" value="1" @checked(request()->boolean('show_inactive'))>
            Ver inactivos
        </label>
        <button class="btn" type="submit">Buscar</button>
    </form>
    @if ($products->isNotEmpty())
        <div style="display:flex;gap:1rem;align-items:center;margin-top:.6rem;font-size:.9rem">
            <label style="display:flex;align-items:center;gap:.4rem;margin:0">
                <input type="checkbox" id="labels-page-all"> Seleccionar esta página para etiquetas
            </label>
            <a href="#" id="labels-clear" class="muted" hidden>Limpiar selección</a>
        </div>
    @endif
</div>

<div class="tree">
    @forelse ($products as $product)
        @php
            $stock = (int) ($product->stock_on_hand ?? 0);
            $supplierCount = $product->productSuppliers->count();
            $avgPurchase = $product->averagePurchasePrice();
            $hasVaryingPrices = $product->hasVaryingPurchasePrices();
            $lowStock = $product->isBelowMinStock($stock);
        @endphp
        <article class="tree-node">
            <div class="tree-product">
                <div class="tree-product-main">
                    <label class="tree-product-code" style="display:flex;align-items:center;gap:.4rem;cursor:pointer">
                        <input type="checkbox" class="label-pick" value="{{ $product->id }}" aria-label="Seleccionar {{ $product->code }} para etiquetas">
                        {{ $product->code }}
                    </label>
                    <a class="tree-product-name" href="{{ route('inventory.products.show', $product) }}">
                        {{ $product->name }}
                    </a>
                    <div class="tree-product-meta">
                        <span class="badge">Venta {{ money($product->salePriceWithVat()) }} c/IVA</span>
                        @if ($product->hasActivePromo())
                            <span class="badge badge-warn">{{ $product->promoBadgeLabel() }} → {{ money($product->effectiveSalePriceWithVat()) }}</span>
                        @endif
                        @if ($product->free_shipping)
                            <span class="badge badge-ok">Envío gratis</span>
                        @endif
                        @if ($product->on_demand)
                            <span class="badge badge-warn">On demand</span>
                        @endif
                        @if ($avgPurchase !== null)
                            <span class="badge {{ $hasVaryingPrices ? 'badge-warn' : '' }}">
                                {{ $hasVaryingPrices ? 'Prom. compra' : 'Compra' }} {{ money($avgPurchase) }}
                            </span>
                        @endif
                        <span class="badge {{ $product->on_demand ? '' : ($lowStock ? 'badge-warn' : 'badge-ok') }}">
                            @if ($product->on_demand)
                                Stock opcional {{ $stock }}
                            @else
                                Stock {{ $stock }} {{ $product->unit }}
                            @endif
                        </span>
                        <span class="badge">Mín. {{ $product->min_stock }}</span>
                        @if ($lowStock)
                            <span class="badge badge-off">Alerta stock</span>
                        @endif
                        <span class="badge {{ $product->is_active ? 'badge-ok' : 'badge-off' }}">
                            {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                </div>
                <div class="tree-product-actions">
                    <a class="btn btn-secondary" href="{{ route('inventory.stock.create', ['product_id' => $product->id]) }}">Stock</a>
                    <a class="btn btn-secondary" href="{{ route('inventory.products.duplicate', $product) }}">Duplicar</a>
                    <a class="btn" href="{{ route('inventory.products.edit', $product) }}">Editar</a>
                </div>
            </div>

            @if ($supplierCount > 0)
                <details class="tree-accordion">
                    <summary class="tree-accordion-toggle">
                        <div class="tree-accordion-summary">
                            <strong>Proveedores ({{ $supplierCount }})</strong>
                            @if ($hasVaryingPrices)
                                <span class="badge badge-warn">Precios distintos · prom. {{ money($avgPurchase) }}</span>
                            @elseif ($avgPurchase !== null)
                                <span class="badge">Compra {{ money($avgPurchase) }}</span>
                            @endif
                        </div>
                        <span class="tree-accordion-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="tree-accordion-panel">
                        <ul class="tree-children">
                            @foreach ($product->productSuppliers as $row)
                                <li class="tree-child">
                                    <div class="tree-child-card">
                                        <div>
                                            <div class="tree-child-title">
                                                {{ $row->supplier?->name ?: 'Proveedor' }}
                                                @if ($row->is_preferred)
                                                    <span class="badge badge-ok">Preferido</span>
                                                @endif
                                            </div>
                                            <div class="muted">{{ $row->supplier?->code }}</div>
                                        </div>
                                        @can('inventory.costs')
                                            <div>
                                                <span class="muted">Compra</span>
                                                <strong style="display:block">{{ money($row->purchase_price) }}</strong>
                                            </div>
                                        @endcan
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </details>
            @else
                <div class="tree-empty">Sin proveedores asociados</div>
            @endif
        </article>
    @empty
        <div class="card muted">Aún no hay productos. Crea el primero para empezar el inventario.</div>
    @endforelse
</div>

<div style="margin-top:1rem">{{ $products->links() }}</div>

<script>
(() => {
    const key = 'rolo-label-picks';
    const form = document.getElementById('labels-form');
    const btn = document.getElementById('labels-btn');
    const count = document.getElementById('labels-count');
    const pageAll = document.getElementById('labels-page-all');
    const clear = document.getElementById('labels-clear');
    const boxes = [...document.querySelectorAll('.label-pick')];
    const load = () => { try { return new Set(JSON.parse(sessionStorage.getItem(key) || '[]')); } catch { return new Set(); } };
    let picks = load();

    const render = () => {
        sessionStorage.setItem(key, JSON.stringify([...picks]));
        boxes.forEach((box) => { box.checked = picks.has(box.value); });
        count.textContent = picks.size;
        btn.disabled = picks.size === 0;
        if (clear) clear.hidden = picks.size === 0;
        if (pageAll) pageAll.checked = boxes.length > 0 && boxes.every((box) => box.checked);
    };

    boxes.forEach((box) => box.addEventListener('change', () => {
        box.checked ? picks.add(box.value) : picks.delete(box.value);
        render();
    }));
    pageAll?.addEventListener('change', () => {
        boxes.forEach((box) => pageAll.checked ? picks.add(box.value) : picks.delete(box.value));
        render();
    });
    clear?.addEventListener('click', (e) => { e.preventDefault(); picks.clear(); render(); });
    form.addEventListener('submit', () => {
        form.querySelectorAll('input[name="ids[]"]').forEach((el) => el.remove());
        picks.forEach((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        });
    });
    render();
})();
</script>
@endsection
