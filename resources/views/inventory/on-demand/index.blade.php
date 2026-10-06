@extends('layouts.app')

@section('title', 'On demand — qué debo comprar')

@section('content')
<div class="topbar">
    <div>
        <h1>On demand — qué debo comprar</h1>
        <p class="muted">
            Unidades entregadas sin lote físico (ventas y consignaciones). Al registrar una entrada de stock,
            el sistema las cubre primero (COGS real) y dejan de aparecer aquí — aunque el cliente o consignatario ya haya pagado.
        </p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('inventory.stock.create') }}">Recibir stock</a>
        <a class="btn btn-secondary" href="{{ route('inventory.payables.index') }}">Cuentas por pagar</a>
    </div>
</div>

@if ($totalQty > 0)
    <div class="flash" style="background:#eff6ff;color:#1e40af;border-color:#bfdbfe;margin-bottom:1rem">
        Debes comprar / recibir <strong>{{ $totalQty }}</strong> unidad(es)
        ({{ $totalFromSales }} en ventas · {{ $totalFromConsignments }} en consignaciones).
        Usa <a href="{{ route('inventory.stock.create') }}">Recibir stock</a> por producto para cubrirlas.
    </div>
@endif

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('inventory.on-demand.index') }}" style="flex-wrap:wrap">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Producto, # venta o consignación">
        <select name="product_id">
            <option value="">Todos los productos on demand</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>
                    {{ $product->code }} — {{ $product->name }}
                </option>
            @endforeach
        </select>
        <select name="source">
            <option value="" @selected(($source ?? '') === '')>Ventas + consignaciones</option>
            <option value="sale" @selected(($source ?? '') === 'sale')>Solo ventas</option>
            <option value="consignment" @selected(($source ?? '') === 'consignment')>Solo consignaciones</option>
        </select>
        <select name="status">
            <option value="">Estado venta (todos)</option>
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
    <div class="card">Unidades a comprar<strong>{{ $totalQty }}</strong></div>
    <div class="card">De ventas<strong>{{ $totalFromSales }}</strong></div>
    <div class="card">De consignaciones<strong>{{ $totalFromConsignments }}</strong></div>
    <div class="card">COGS estimado<strong>{{ money($totalCogs) }}</strong></div>
    <div class="card">Productos<strong>{{ $summary->count() }}</strong></div>
</div>

<div class="card" style="margin-bottom:1rem">
    <h2 style="margin-top:0;font-size:1.1rem">Resumen por producto</h2>
    <p class="muted" style="margin-top:0">Esta es la lista de lo que debes: cuánto y de qué producto.</p>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant. a comprar</th>
                <th>Ventas</th>
                <th>Consignaciones</th>
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
                    <td><strong>{{ (int) $row->qty_pending }}</strong></td>
                    <td>{{ (int) $row->from_sales }}</td>
                    <td>{{ (int) $row->from_consignments }}</td>
                    <td>{{ money($row->cogs_estimated) }}</td>
                    <td class="actions">
                        <a href="{{ route('inventory.stock.create', ['product_id' => $row->product_id]) }}">Recibir stock</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No hay unidades on demand pendientes. Nada por comprar por este concepto.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h2 style="margin-top:0;font-size:1.1rem">Detalle (ventas y consignaciones)</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Origen</th>
                <th>Documento</th>
                <th>Estado</th>
                <th>Cliente / consignatario</th>
                <th>Producto</th>
                <th>Cant.</th>
                <th>Costo est.</th>
                <th>COGS</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ $line['occurred_at']?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>
                        @if ($line['source'] === 'consignment')
                            <span class="badge badge-warn">Consignación</span>
                        @else
                            <span class="badge">Venta</span>
                        @endif
                    </td>
                    <td>
                        @if ($line['document_url'])
                            <a href="{{ $line['document_url'] }}">{{ $line['document_number'] }}</a>
                        @else
                            {{ $line['document_number'] ?? '—' }}
                        @endif
                    </td>
                    <td>
                        @if ($line['status_label'])
                            <span class="badge {{ $line['status_badge'] }}">{{ $line['status_label'] }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $line['party_name'] }}</td>
                    <td>
                        @if ($line['product_id'])
                            <a href="{{ route('inventory.products.show', $line['product_id']) }}">
                                {{ $line['product_code'] }} — {{ $line['product_name'] }}
                            </a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ (int) $line['quantity'] }}</td>
                    <td>{{ money($line['purchase_price']) }}</td>
                    <td>{{ money($line['cogs_amount']) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Sin líneas on demand pendientes.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $lines->links() }}</div>
</div>
@endsection
