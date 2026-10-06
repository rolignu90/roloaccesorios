@extends('layouts.app')

@section('title', 'Dashboard inventario')

@section('content')
@php
    $purchaseMax = max(1, (float) $purchase_months->max('amount'));
    $potentialMargin = $retail_value - $inventory_value;
@endphp

<div class="topbar">
    <div>
        <h1>Dashboard de inventario</h1>
        <p class="muted">Valor en stock, alertas y compras (USD)</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('inventory.stock.index') }}">Entradas</a>
        <a class="btn" href="{{ route('inventory.stock.create') }}">Recibir stock</a>
    </div>
</div>

@if ($low_stock_count > 0)
    <div class="card" style="margin-bottom:1rem;border-color:#f1aeb5;background:#fff5f5">
        <strong style="color:var(--danger)">{{ $low_stock_count }} producto(s) en o bajo stock mínimo</strong>
        <span class="muted"> — revisa la lista de alertas abajo o recibe stock.</span>
    </div>
@endif

<div class="meta" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
@can('inventory.costs')
    <div class="card">
        Valor inventario (costo)
        <strong>{{ money($inventory_value) }}</strong>
        <span class="muted">FIFO · lotes actuales</span>
    </div>
    <div class="card">
        Valor venta c/IVA
        <strong>{{ money($retail_value) }}</strong>
        <span class="muted">margen potencial {{ money($potentialMargin) }}</span>
    </div>
@endcan
    <div class="card">
        Unidades en stock
        <strong>{{ number_format($units_on_hand) }}</strong>
        <span class="muted">{{ $active_products }} productos activos</span>
    </div>
    <div class="card">
        Alertas stock mín.
        <strong style="{{ $low_stock_count > 0 ? 'color:var(--danger)' : '' }}">{{ $low_stock_count }}</strong>
        <span class="muted">{{ $out_of_stock }} sin stock</span>
    </div>
    <div class="card">
        Compras este mes
        <strong>{{ money($purchases_this_month['amount']) }}</strong>
        <span class="muted">
            {{ number_format($purchases_this_month['units']) }} uds · {{ $purchases_this_month['receipts'] }} entradas
            @if ($purchases_change_percent !== null)
                ·
                <span style="color:{{ $purchases_change_percent >= 0 ? 'var(--ok)' : 'var(--danger)' }}">
                    {{ $purchases_change_percent >= 0 ? '+' : '' }}{{ $purchases_change_percent }}% vs mes ant.
                </span>
            @endif
        </span>
    </div>
    <div class="card">
        Compras mes anterior
        <strong>{{ money($purchases_last_month['amount']) }}</strong>
        <span class="muted">{{ number_format($purchases_last_month['units']) }} uds · {{ $purchases_last_month['receipts'] }} entradas</span>
    </div>
</div>

@can('inventory.costs')
<div class="card" style="margin-bottom:1rem">
    <h2 style="margin-top:0;font-size:1.1rem">Compras por mes</h2>
    <p class="muted" style="margin-top:0">Últimos 6 meses · costo de entradas de stock</p>
    <div style="display:grid;grid-template-columns:repeat({{ max(1, $purchase_months->count()) }},minmax(0,1fr));gap:.75rem;align-items:end;min-height:160px;margin:1rem 0 .5rem">
        @foreach ($purchase_months as $month)
            @php $height = max(4, round(($month->amount / $purchaseMax) * 120)); @endphp
            <div style="display:flex;flex-direction:column;align-items:center;gap:.4rem;min-width:0">
                <span style="font-size:.78rem;font-weight:600">{{ money($month->amount) }}</span>
                <div style="width:100%;max-width:56px;height:{{ $height }}px;background:linear-gradient(180deg,var(--signal),#111);border-radius:.35rem .35rem 0 0" title="{{ $month->units }} uds"></div>
                <span class="muted" style="font-size:.75rem;text-align:center;text-transform:capitalize">{{ $month->label }}</span>
            </div>
        @endforeach
    </div>
    <table>
        <thead>
            <tr>
                <th>Mes</th>
                <th>Monto</th>
                <th>Unidades</th>
                <th>Entradas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchase_months->reverse() as $month)
                <tr>
                    <td style="text-transform:capitalize">{{ $month->label }}</td>
                    <td>{{ money($month->amount) }}</td>
                    <td>{{ number_format($month->units) }}</td>
                    <td>{{ $month->receipts }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endcan

<div class="grid-2">
    <div class="card">
        <div class="topbar" style="margin-bottom:.75rem">
            <h2 style="margin:0;font-size:1.1rem">Alertas de stock mínimo</h2>
            <a class="btn-link" href="{{ route('inventory.products.index') }}">Ver productos</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Stock</th>
                    <th>Mín.</th>
                    <th>Proveedor</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($low_stock_products as $product)
                    @php
                        $preferred = $product->productSuppliers->firstWhere('is_preferred', true)
                            ?? $product->productSuppliers->first();
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('inventory.products.show', $product) }}">{{ $product->code }}</a>
                            <div class="muted">{{ $product->name }}</div>
                        </td>
                        <td>
                            <span class="badge badge-off">{{ (int) $product->stock_on_hand }}</span>
                        </td>
                        <td>{{ (int) $product->min_stock }}</td>
                        <td class="muted">{{ $preferred?->supplier?->name ?? '—' }}</td>
                        <td><a href="{{ route('inventory.stock.create', ['product_id' => $product->id]) }}">Recibir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Sin alertas: ningún producto activo está en o bajo su mínimo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

@can('inventory.costs')
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Top por valor en stock</h2>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cant.</th>
                    <th>Valor costo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($top_by_value as $row)
                    <tr>
                        <td>
                            <a href="{{ route('inventory.products.show', $row->id) }}">{{ $row->code }}</a>
                            <div class="muted">{{ $row->name }}</div>
                        </td>
                        <td>{{ number_format((int) $row->qty) }} {{ $row->unit }}</td>
                        <td>{{ money($row->inventory_value) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">No hay stock disponible.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endcan
</div>

<div class="grid-2" style="margin-top:1rem">
@can('inventory.costs')
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Compras por proveedor · este mes</h2>
        <table>
            <thead>
                <tr>
                    <th>Proveedor</th>
                    <th>Monto</th>
                    <th>Uds.</th>
                    <th>Entradas</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($purchases_by_supplier as $row)
                    <tr>
                        <td>
                            <a href="{{ route('inventory.suppliers.show', $row->id) }}">{{ $row->code }}</a>
                            <div class="muted">{{ $row->name }}</div>
                        </td>
                        <td>{{ money($row->amount) }}</td>
                        <td>{{ number_format((int) $row->units) }}</td>
                        <td>{{ (int) $row->receipts }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Sin compras este mes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endcan

    <div class="card">
        <div class="topbar" style="margin-bottom:.75rem">
            <h2 style="margin:0;font-size:1.1rem">Movimientos recientes</h2>
            <a class="btn-link" href="{{ route('inventory.movements.index') }}">Ver historial</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Producto</th>
                    <th>Cant.</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recent_movements as $movement)
                    <tr>
                        <td>{{ $movement->occurred_at?->format('d/m H:i') }}</td>
                        <td>{{ $movement->typeLabel() }}</td>
                        <td>
                            @if ($movement->product)
                                <a href="{{ route('inventory.products.show', $movement->product) }}">{{ $movement->product->code }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td style="color:{{ $movement->quantity >= 0 ? 'var(--ok)' : 'var(--danger)' }}">
                            {{ $movement->quantity >= 0 ? '+' : '' }}{{ $movement->quantity }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Sin movimientos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
