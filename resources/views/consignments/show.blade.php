@extends('layouts.app')

@section('title', $consignment->number)

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $consignment->number }}</h1>
        <p class="muted">
            {{ $consignment->delivered_at?->format('d/m/Y H:i') }} ·
            {{ $consignment->partyLabel() }}
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('consignments.index') }}">Lista</a>
        @if ($consignment->canVoid())
            <form method="POST" action="{{ route('consignments.void', $consignment) }}" onsubmit="return confirm('¿Anular consignación y restaurar stock pendiente?')">
                @csrf
                <button class="btn btn-danger" type="submit">Anular</button>
            </form>
        @endif
    </div>
</div>

@if ($errors->has('consignment') || $errors->has('payment') || $errors->has('return'))
    <div class="errors" style="margin-bottom:1rem">
        {{ $errors->first('consignment') ?: ($errors->first('payment') ?: $errors->first('return')) }}
    </div>
@endif

<div class="meta" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
    <div class="card">
        Estado
        <strong>
            <span class="badge {{ $consignment->isVoided() ? 'badge-off' : ($consignment->status === 'settled' ? 'badge-ok' : 'badge-warn') }}">
                {{ $consignment->statusLabel() }}
            </span>
        </strong>
    </div>
    <div class="card">Entregado<strong>{{ money($consignment->total_with_vat) }}</strong></div>
    <div class="card">Devuelto<strong>{{ money($consignment->returned_with_vat) }}</strong></div>
    <div class="card">Pagado<strong>{{ money($consignment->paid_with_vat) }}</strong></div>
    <div class="card">Saldo adeudado<strong style="color:var(--danger)">{{ money($consignment->balance_with_vat) }}</strong></div>
</div>

@if ($consignment->notes)
    <div class="card" style="margin-bottom:1rem"><strong>Notas:</strong> {{ $consignment->notes }}</div>
@endif

<div class="card" style="margin-bottom:1rem">
    <h2 style="margin-top:0;font-size:1.1rem">Productos</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Entregado</th>
                <th>Devuelto</th>
                <th>Pendiente</th>
                <th>Precio c/IVA</th>
                <th>Total línea</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($consignment->items as $item)
                <tr>
                    <td>{{ $item->product?->code }} — {{ $item->product?->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->quantity_returned }}</td>
                    <td>{{ $item->quantityOutstanding() }}</td>
                    <td>{{ money($item->unit_price_with_vat) }}</td>
                    <td>{{ money($item->line_total_with_vat) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="grid-2">
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Pagos</h2>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Monto</th>
                    <th>Método</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($consignment->payments as $payment)
                    <tr>
                        <td>{{ $payment->paid_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ money($payment->amount) }}</td>
                        <td>{{ $paymentMethods[$payment->method] ?? $payment->method }}</td>
                        <td>{{ $payment->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Sin pagos.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($consignment->canReceivePayment())
            <form method="POST" action="{{ route('consignments.payments.store', $consignment) }}" style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--line)">
                @csrf
                <strong style="display:block;margin-bottom:.75rem">Registrar pago</strong>
                <div class="grid-2">
                    <div class="field">
                        <label for="paid_at">Fecha *</label>
                        <input id="paid_at" type="datetime-local" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d\\TH:i')) }}" required>
                    </div>
                    <div class="field">
                        <label for="amount">Monto c/IVA *</label>
                        <input id="amount" type="number" min="0.01" step="0.01" name="amount" value="{{ old('amount', number_format($consignment->balance_with_vat, 2, '.', '')) }}" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="field">
                        <label for="method">Método *</label>
                        <select id="method" name="method" required>
                            @foreach ($paymentMethods as $key => $label)
                                <option value="{{ $key }}" @selected(old('method', 'cash') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="payment_notes">Notas</label>
                        <input id="payment_notes" type="text" name="notes" value="{{ old('notes') }}">
                    </div>
                </div>
                <button class="btn" type="submit">Guardar pago</button>
            </form>
        @endif
    </div>

    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Devoluciones</h2>
        @forelse ($consignment->returns as $return)
            <div style="margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid var(--line)">
                <strong>{{ $return->returned_at?->format('d/m/Y H:i') }}</strong>
                — {{ money($return->total_with_vat) }}
                @if ($return->notes)
                    <span class="muted">· {{ $return->notes }}</span>
                @endif
                <ul style="margin:.4rem 0 0;padding-left:1.1rem">
                    @foreach ($return->items as $rItem)
                        <li>
                            {{ $rItem->consignmentItem?->product?->code }}:
                            {{ $rItem->quantity }} × {{ money($rItem->unit_price_with_vat) }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="muted">Sin devoluciones.</p>
        @endforelse

        @if ($consignment->canReturn())
            <form method="POST" action="{{ route('consignments.returns.store', $consignment) }}" style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--line)">
                @csrf
                <strong style="display:block;margin-bottom:.75rem">Registrar devolución</strong>
                <div class="field">
                    <label for="returned_at">Fecha *</label>
                    <input id="returned_at" type="datetime-local" name="returned_at" value="{{ old('returned_at', now()->format('Y-m-d\\TH:i')) }}" required>
                </div>
                @foreach ($consignment->items as $index => $item)
                    @if ($item->quantityOutstanding() > 0)
                        <div class="grid-2" style="align-items:end">
                            <div class="field">
                                <label>{{ $item->product?->code }} — pendiente {{ $item->quantityOutstanding() }}</label>
                                <input type="hidden" name="items[{{ $index }}][consignment_item_id]" value="{{ $item->id }}">
                                <input type="number" min="0" max="{{ $item->quantityOutstanding() }}" name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity', 0) }}">
                            </div>
                            <p class="muted" style="margin:0 0 .9rem">Máx. {{ $item->quantityOutstanding() }}</p>
                        </div>
                    @endif
                @endforeach
                <div class="field">
                    <label for="return_notes">Motivo / notas</label>
                    <input id="return_notes" type="text" name="notes" value="{{ old('notes') }}">
                </div>
                <button class="btn btn-secondary" type="submit">Guardar devolución</button>
            </form>
        @endif
    </div>
</div>
@endsection
