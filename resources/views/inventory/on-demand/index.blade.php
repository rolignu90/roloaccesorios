@extends('layouts.app')

@section('title', 'Ventas on demand')

@section('content')
<div class="topbar">
    <div>
        <h1>Ventas on demand</h1>
        <p class="muted">Pendiente de comprar: ventas sin lote físico. Al registrar una entrada de stock, el sistema cubre estas unidades primero (COGS real) y dejan de aparecer aquí.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('inventory.stock.create') }}">Recibir stock</a>
        <a class="btn btn-secondary" href="{{ route('inventory.payables.index') }}">Cuentas por pagar</a>
    </div>
</div>

@if ($totalQty > 0)
    <div class="flash" style="background:#eff6ff;color:#1e40af;border-color:#bfdbfe;margin-bottom:1rem">
        Hay <strong>{{ $totalQty }}</strong> unidad(es) pendientes.
        Usa <a href="{{ route('inventory.stock.create') }}">Recibir stock</a> por producto para cubrirlas; la deuda al proveedor nace con esa entrada.
    </div>
@endif

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('inventory.on-demand.index') }}" style="flex-wrap:wrap">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Producto o # venta">
        <select name="product_id">
            <option value="">Todos los productos on demand</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>
                    {{ $product->code }} — {{ $product->name }}
                </option>
            @endforeach
        </select>
        <select name="status">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\Sale::ON_DEMAND_STATUSES as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>
                    {{ \App\Models\Sale::STATUS_LABELS[$status] }}
                </option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}">
        <input type="date" name="to" value="{{ request('to') }}">
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="meta" style="margin-bottom:1rem">
    <div class="card">Unidades pendientes<strong>{{ $totalQty }}</strong></div>
    <div class="card">COGS estimado<strong>{{ money($totalCogs) }}</strong></div>
    <div class="card">Productos<strong>{{ $summary->count() }}</strong></div>
</div>

<div class="card" style="margin-bottom:1rem">
    <h2 style="margin-top:0;font-size:1.1rem">Resumen por producto</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant. a comprar</th>
                <th>COGS estimado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($summary as $row)
                <tr>
                    <td>
                        <a href="{{ route('inventory.products.show', $row->product_id) }}">
                            {{ $row->code }} — {{ $row->name }}
                        </a>
                    </td>
                    <td>{{ (int) $row->qty_pending }}</td>
                    <td>{{ money($row->cogs_estimated) }}</td>
                    <td class="actions">
                        <a href="{{ route('inventory.stock.create', ['product_id' => $row->product_id]) }}">Recibir stock</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No hay ventas on demand en el período.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h2 style="margin-top:0;font-size:1.1rem">Detalle por venta</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Venta</th>
                <th>Estado</th>
                <th>Cliente</th>
                <th>Producto</th>
                <th>Cant. on demand</th>
                <th>Costo est.</th>
                <th>COGS</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                @php
                    $item = $line->saleItem;
                    $sale = $item?->sale;
                    $product = $item?->product;
                @endphp
                <tr>
                    <td>{{ $sale?->sold_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        @if ($sale)
                            <a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if ($sale)
                            <span class="badge {{ $sale->statusBadgeClass() }}">{{ $sale->statusLabel() }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $sale?->customer?->name ?? '—' }}</td>
                    <td>
                        @if ($product)
                            <a href="{{ route('inventory.products.show', $product) }}">
                                {{ $product->code }} — {{ $product->name }}
                            </a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ (int) $line->quantity }}</td>
                    <td>{{ money($line->purchase_price) }}</td>
                    <td>{{ money($line->cogs_amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Sin líneas on demand.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $lines->links() }}</div>
</div>
@endsection
