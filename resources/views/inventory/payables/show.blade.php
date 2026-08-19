@extends('layouts.app')

@section('title', 'Proveedor '.$supplier->name)

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $supplier->code }} — {{ $supplier->name }}</h1>
        <p class="muted">Detalle de lotes y pagos</p>
    </div>
    <div class="actions">
        @if ($balance > 0.009)
            <form method="POST" action="{{ route('inventory.payables.pay-full', $supplier) }}" style="display:inline"
                  onsubmit="return confirm('¿Pagar completo {{ money($balance) }} a {{ $supplier->name }}?')">
                @csrf
                <button class="btn" type="submit">Pagar completo ({{ money($balance) }})</button>
            </form>
            <a class="btn btn-secondary" href="{{ route('inventory.payables.create', ['supplier_id' => $supplier->id]) }}">Pago parcial</a>
        @endif
        <a class="btn btn-secondary" href="{{ route('inventory.payables.index') }}">Volver</a>
    </div>
</div>

<div class="meta" style="margin-bottom:1rem">
    <div class="card">Comprado<strong>{{ money($purchased) }}</strong></div>
    <div class="card">Pagado<strong>{{ money($paid) }}</strong></div>
    <div class="card">Adeudado<strong>{{ money($balance) }}</strong></div>
</div>

<div class="card" style="margin-bottom:1rem">
    <h2 style="margin-top:0;font-size:1.1rem">Lotes / compras</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Lote</th>
                <th>Producto</th>
                <th>Cant.</th>
                <th>Costo unit.</th>
                <th>Total</th>
                <th>Pagado</th>
                <th>Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lots as $lot)
                <tr>
                    <td>{{ $lot->received_at?->format('d/m/Y') }}</td>
                    <td>{{ $lot->lot_number }}</td>
                    <td>{{ $lot->product?->code }} — {{ $lot->product?->name }}</td>
                    <td>{{ (int) $lot->quantity_received }}</td>
                    <td>{{ money($lot->purchase_price) }}</td>
                    <td>{{ money($lot->purchaseCost()) }}</td>
                    <td>{{ money($lot->amount_paid) }}</td>
                    <td>
                        @if ($lot->balanceDue() > 0.009)
                            <span class="badge badge-warn">{{ money($lot->balanceDue()) }}</span>
                        @else
                            <span class="badge badge-ok">Pagado</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Sin lotes.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <h2 style="margin-top:0;font-size:1.1rem">Pagos</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Monto</th>
                <th>Método</th>
                <th>Referencia</th>
                <th>Aplicado a</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                <tr>
                    <td>{{ $payment->paid_at?->format('d/m/Y H:i') }}</td>
                    <td><strong>{{ money($payment->amount) }}</strong></td>
                    <td>{{ config('sales.payment_methods')[$payment->payment_method] ?? $payment->payment_method }}</td>
                    <td>{{ $payment->reference ?: '—' }}</td>
                    <td>
                        @foreach ($payment->allocations as $alloc)
                            <div class="muted" style="font-size:.9rem">
                                {{ $alloc->inventoryLot?->lot_number }}
                                ({{ $alloc->inventoryLot?->product?->code }})
                                · {{ money($alloc->amount) }}
                            </div>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Sin pagos aún.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $payments->links() }}</div>
</div>
@endsection
