@extends('layouts.app')

@section('title', 'Registrar pago a proveedor')

@section('content')
<div class="topbar">
    <div>
        <h1>Registrar pago</h1>
        <p class="muted">Se aplica a los lotes impagos del proveedor (FIFO, o los que marques)</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('inventory.payables.index') }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('inventory.payables.store') }}" id="supplier-payment-form">
        @csrf
        <div class="grid-2">
            <div class="field">
                <label for="supplier_id">Proveedor *</label>
                <select id="supplier_id" name="supplier_id" required onchange="window.location='{{ route('inventory.payables.create') }}?supplier_id='+this.value">
                    <option value="">— Selecciona —</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) $selectedSupplierId === (string) $supplier->id)>
                            {{ $supplier->code }} — {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="paid_at">Fecha de pago *</label>
                <input id="paid_at" type="datetime-local" name="paid_at"
                    value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}" required>
            </div>
        </div>
        <div class="grid-3">
            <div class="field">
                <label for="amount">Monto (USD) *</label>
                <input id="amount" type="number" min="0.01" step="0.01" name="amount"
                    value="{{ old('amount', $lots->sum(fn ($l) => $l->balanceDue())) }}" required>
            </div>
            <div class="field">
                <label for="payment_method">Método *</label>
                <select id="payment_method" name="payment_method" required>
                    @foreach ($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method', 'transfer') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="reference">Referencia</label>
                <input id="reference" type="text" name="reference" value="{{ old('reference') }}" placeholder="Nº transferencia / cheque">
            </div>
        </div>
        <div class="field">
            <label for="notes">Notas</label>
            <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
        </div>

        @if ($lots->isNotEmpty())
            <div class="card" style="margin:1rem 0;padding:1rem;background:#f9fafb">
                <strong>Lotes con saldo</strong>
                <p class="muted" style="margin:.35rem 0 .75rem">Si no marcas ninguno, se aplica en orden FIFO a todos.</p>
                <table>
                    <thead>
                        <tr>
                            <th></th>
                            <th>Lote</th>
                            <th>Producto</th>
                            <th>Fecha</th>
                            <th>Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lots as $lot)
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        name="lot_ids[]"
                                        value="{{ $lot->id }}"
                                        @checked(collect(old('lot_ids', []))->contains($lot->id))
                                    >
                                </td>
                                <td>{{ $lot->lot_number }}</td>
                                <td>{{ $lot->product?->code }} — {{ $lot->product?->name }}</td>
                                <td>{{ $lot->received_at?->format('d/m/Y') }}</td>
                                <td>{{ money($lot->balanceDue()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif ($selectedSupplierId)
            <p class="muted">Este proveedor no tiene saldo pendiente.</p>
        @endif

        <button class="btn" type="submit" @disabled($lots->isEmpty())>Guardar pago</button>
    </form>
</div>
@endsection
