@extends('layouts.app')

@section('title', $product->name)

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $product->name }}</h1>
        <p class="muted">Código {{ $product->code }}
            @if ($product->free_shipping)
                · <span class="badge badge-ok">Envío gratis</span>
            @endif
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('inventory.stock.create', ['product_id' => $product->id]) }}">Entrada de stock</a>
        <a class="btn btn-secondary" href="{{ route('inventory.products.duplicate', $product) }}">Duplicar</a>
        <a class="btn" href="{{ route('inventory.products.edit', $product) }}">Editar</a>
    </div>
</div>

<div class="meta">
    <div class="card">
        Stock actual
        <strong>{{ $stockOnHand }} {{ $product->unit }}</strong>
        @if ($product->isBelowMinStock($stockOnHand))
            <span class="badge badge-off" style="margin-top:.5rem">Alerta: en o bajo el mínimo</span>
        @endif
    </div>
    <div class="card">
        Stock mínimo
        <strong>{{ $product->min_stock }} {{ $product->unit }}</strong>
        <span class="muted" style="display:block;margin-top:.35rem;font-size:.85rem">Umbral de alerta</span>
    </div>
    <div class="card">
        Precio venta c/IVA (USD)
        <strong>{{ money($product->salePriceWithVat()) }}</strong>
        <span class="muted" style="display:block;margin-top:.35rem;font-size:.85rem">
            Base s/IVA {{ money($product->sale_price_without_vat) }}
        </span>
        @if ($product->hasActivePromo())
            <span class="badge badge-warn" style="margin-top:.5rem">{{ $product->promoBadgeLabel() }}</span>
            <strong style="display:block;margin-top:.35rem">Efectivo {{ money($product->effectiveSalePriceWithVat()) }} c/IVA</strong>
        @endif
    </div>
    <div class="card">
        Precio reventa / mayoreo c/IVA
        <strong>
            @if ($product->wholesalePriceWithVat() !== null)
                {{ money($product->wholesalePriceWithVat()) }}
            @else
                —
            @endif
        </strong>
        <span class="muted" style="display:block;margin-top:.35rem;font-size:.85rem">Para consignación</span>
    </div>
    <div class="card">
        {{ $product->hasVaryingPurchasePrices() ? 'Prom. compra (USD)' : 'Precio compra (USD)' }}
        <strong>
            @if ($product->averagePurchasePrice() !== null)
                {{ money($product->averagePurchasePrice()) }}
            @else
                —
            @endif
        </strong>
    </div>
</div>

@if ($product->isBelowMinStock($stockOnHand))
    <div class="errors" style="margin-bottom:1rem">
        <strong>Alerta de stock:</strong>
        Hay {{ $stockOnHand }} {{ $product->unit }} y el mínimo es {{ $product->min_stock }}.
        <a href="{{ route('inventory.stock.create', ['product_id' => $product->id]) }}">Registrar entrada de stock</a>
    </div>
@endif

<div class="grid-2" style="margin-bottom:1rem">
    <div class="card">
        <p><strong>Unidad:</strong> {{ $product->unit }}</p>
        <p><strong>Estado:</strong> {{ $product->is_active ? 'Activo' : 'Inactivo' }}</p>
        <p><strong>Envío:</strong> {{ $product->free_shipping ? 'Gratis' : 'Normal' }}</p>
        <p><strong>Descripción:</strong> {{ $product->description ?: '—' }}</p>
        <p class="muted">
            Un solo precio de venta. Los precios de compra dependen del proveedor y del lote FIFO.
        </p>
    </div>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Proveedores</h2>
        @if ($product->productSuppliers->isNotEmpty())
            @php
                $avgPurchase = $product->averagePurchasePrice();
                $hasVaryingPrices = $product->hasVaryingPurchasePrices();
            @endphp
            <div class="tree-product-meta" style="margin-bottom:.75rem">
                @if ($hasVaryingPrices)
                    <span class="badge badge-warn">Precios distintos · prom. {{ money($avgPurchase) }}</span>
                @else
                    <span class="badge">Compra {{ money($avgPurchase) }}</span>
                @endif
            </div>
            <details class="tree-accordion" open style="border:1px solid var(--line);border-radius:.55rem">
                <summary class="tree-accordion-toggle">
                    <div class="tree-accordion-summary">
                        <strong>Ver proveedores ({{ $product->productSuppliers->count() }})</strong>
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
                                            {{ $row->supplier?->name ?: '—' }}
                                            @if ($row->is_preferred)
                                                <span class="badge badge-ok">Preferido</span>
                                            @endif
                                        </div>
                                        <div class="muted">{{ $row->supplier?->code }}</div>
                                    </div>
                                    <div>
                                        <span class="muted">Compra</span>
                                        <strong style="display:block">{{ money($row->purchase_price) }}</strong>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </details>
        @else
            <p class="muted">Sin proveedores asociados.</p>
        @endif
    </div>
</div>

<div class="card">
    <h2 style="margin-top:0;font-size:1.1rem">Lotes FIFO</h2>
    <table>
        <thead>
            <tr>
                <th>Lote</th>
                <th>Recibido</th>
                <th>Proveedor</th>
                <th>P. compra (USD)</th>
                <th>Recibido</th>
                <th>Restante</th>
                <th>Factura</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($product->inventoryLots as $lot)
                <tr>
                    <td>{{ $lot->lot_number }}</td>
                    <td>{{ $lot->received_at->format('d/m/Y') }}</td>
                    <td>{{ $lot->supplier?->name ?: '—' }}</td>
                    <td>{{ money($lot->purchase_price) }}</td>
                    <td>{{ $lot->quantity_received }}</td>
                    <td>
                        <span class="badge {{ $lot->quantity_remaining > 0 ? 'badge-ok' : 'badge-off' }}">
                            {{ $lot->quantity_remaining }}
                        </span>
                    </td>
                    <td>{{ $lot->invoice_reference ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">Sin lotes. Registra una entrada de stock para este producto.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
