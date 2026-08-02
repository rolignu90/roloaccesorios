@extends('layouts.app')

@section('title', 'Rectificar entrada')

@section('content')
<div class="topbar">
    <div>
        <h1>Rectificar entrada</h1>
        <p class="muted">Lote {{ $lot->lot_number }} · {{ $lot->product?->code }} — {{ $lot->product?->name }}</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('inventory.stock.index') }}">Volver</a>
</div>

@if (! $unused)
    <div class="errors" style="margin-bottom:1rem">
        <strong>Este lote ya tiene salidas ({{ $soldQty }} unidades vendidas).</strong>
        Solo puedes ajustar la cantidad restante. No se puede anular la entrada completa.
    </div>
@endif

<div class="card">
    <form method="POST" action="{{ route('inventory.stock.update', $lot) }}">
        @csrf
        @method('PUT')

        @if ($unused)
            <div class="grid-2">
                <div class="field">
                    <label for="supplier_id">Proveedor *</label>
                    <select id="supplier_id" name="supplier_id" required>
                        @foreach ($lot->product->productSuppliers as $row)
                            <option value="{{ $row->supplier_id }}" @selected(old('supplier_id', $lot->supplier_id) == $row->supplier_id)>
                                {{ $row->supplier?->code }} — {{ $row->supplier?->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="received_at">Fecha de recepción *</label>
                    <input id="received_at" type="date" name="received_at" value="{{ old('received_at', $lot->received_at?->toDateString()) }}" required>
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="quantity">Cantidad *</label>
                    <input id="quantity" type="number" min="1" name="quantity" value="{{ old('quantity', $lot->quantity_received) }}" required>
                </div>
                <div class="field">
                    <label for="purchase_price">Precio de compra (USD) *</label>
                    <input id="purchase_price" type="number" min="0" step="0.01" name="purchase_price" value="{{ old('purchase_price', $lot->purchase_price) }}" required>
                </div>
            </div>
            <div class="field">
                <label for="invoice_reference">Factura / referencia</label>
                <input id="invoice_reference" type="text" name="invoice_reference" value="{{ old('invoice_reference', $lot->invoice_reference) }}">
            </div>
        @else
            <div class="meta" style="margin-bottom:1rem">
                <div class="card">Recibido<strong>{{ $lot->quantity_received }}</strong></div>
                <div class="card">Vendido<strong>{{ $soldQty }}</strong></div>
                <div class="card">Restante actual<strong>{{ $lot->quantity_remaining }}</strong></div>
            </div>
            <div class="field">
                <label for="quantity_remaining">Nueva cantidad restante *</label>
                <input id="quantity_remaining" type="number" min="0" name="quantity_remaining" value="{{ old('quantity_remaining', $lot->quantity_remaining) }}" required>
                <p class="muted" style="margin:.35rem 0 0">Si aumentas, se suma stock. Si reduces, no puede quedar bajo 0.</p>
            </div>
        @endif

        <div class="field">
            <label for="notes">Motivo / notas</label>
            <textarea id="notes" name="notes" placeholder="Ej. Se capturó mal la cantidad">{{ old('notes') }}</textarea>
        </div>

        <button class="btn" type="submit">Guardar rectificación</button>
    </form>

    @if ($unused)
        <form method="POST" action="{{ route('inventory.stock.destroy', $lot) }}" style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--line)" onsubmit="return confirm('¿Anular esta entrada por completo?')">
            @csrf
            @method('DELETE')
            <div class="field">
                <label for="reason">Anular entrada (solo si no se ha vendido nada)</label>
                <input id="reason" type="text" name="reason" placeholder="Motivo de anulación">
            </div>
            <button class="btn btn-danger" type="submit">Anular entrada</button>
        </form>
    @endif
</div>
@endsection
