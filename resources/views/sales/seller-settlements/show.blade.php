@extends('layouts.app')

@section('title', $settlement->number)

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $settlement->number }}</h1>
        <p class="muted">
            {{ $settlement->seller?->code }} — {{ $settlement->seller?->name }}
            · {{ $settlement->sellerTypeLabel() }}
            · {{ $settlement->periodLabel() }}
            ({{ $settlement->period_from->format('d/m/Y') }} – {{ $settlement->period_to->format('d/m/Y') }})
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('sales.seller-settlements.index') }}">Historial</a>
        <a class="btn btn-secondary" href="{{ route('sales.seller-settlements.create', ['seller_id' => $settlement->seller_id]) }}">Nueva</a>
        @unless ($settlement->isVoided())
            <form method="POST" action="{{ route('sales.seller-settlements.void', $settlement) }}" onsubmit="return confirm('¿Anular esta liquidación? Las ventas volverán a estar disponibles.')">
                @csrf
                <button class="btn" type="submit" style="background:var(--danger)">Anular</button>
            </form>
        @endunless
    </div>
</div>

@if (session('success'))
    <div class="flash" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="flash" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-bottom:1rem">{{ $errors->first() }}</div>
@endif

<div class="meta" style="margin-bottom:1rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
    <div class="card">Estado
        <strong>
            <span class="badge {{ $settlement->isVoided() ? 'badge-off' : 'badge-ok' }}">
                {{ $settlement->isVoided() ? 'Anulada' : 'Pagada' }}
            </span>
        </strong>
    </div>
    <div class="card">A pagar<strong>{{ money($settlement->amount_due) }}</strong></div>
    <div class="card">A consignación<strong>{{ money($settlement->applied_to_consignments) }}</strong></div>
    <div class="card">Efectivo<strong>{{ money($settlement->cash_paid) }}</strong></div>
    <div class="card">Pagado<strong>{{ optional($settlement->paid_at)->format('d/m/Y H:i') ?: '—' }}</strong></div>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="grid-2">
        <div>
            <p><strong>Ventas:</strong> {{ $settlement->sales_count }} · Total {{ money($settlement->sales_total) }}</p>
            <p>COGS ventas: {{ money($settlement->sales_cogs) }}</p>
            <p>Margen real c/IVA: {{ money($settlement->sales_real_margin_with_vat) }}</p>
            <p>
                <strong>Cliente vinculado:</strong>
                @if ($settlement->linkedCustomer)
                    {{ $settlement->linkedCustomer->code }} — {{ $settlement->linkedCustomer->name }}
                @else
                    —
                @endif
            </p>
        </div>
        <div>
            <p><strong>Devoluciones:</strong> {{ $settlement->returns_count }}</p>
            <p>Costo devoluciones: {{ money($settlement->returns_cost_total) }}</p>
            @if ($settlement->seller_type === 'internal')
                <p>Salario: {{ money($settlement->salary_amount) }}</p>
                <p>Comisión {{ number_format((float) $settlement->commission_percent, 2) }}%: {{ money($settlement->commission_amount) }}</p>
            @endif
            <p>Método efectivo: {{ $settlement->payment_method ?: '—' }}</p>
        </div>
    </div>
    @if ($settlement->notes)
        <p style="margin-bottom:0"><strong>Notas:</strong> {{ $settlement->notes }}</p>
    @endif
</div>

@if ($settlement->consignmentPayments->isNotEmpty())
    <div class="card" style="margin-bottom:1rem">
        <h2 style="margin-top:0;font-size:1.1rem">Pagos aplicados a consignación</h2>
        <table>
            <thead>
                <tr>
                    <th>Consignación</th>
                    <th>Monto</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($settlement->consignmentPayments as $payment)
                    <tr>
                        <td>
                            @if ($payment->consignment)
                                <a href="{{ route('consignments.show', $payment->consignment) }}">{{ $payment->consignment->number }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ money($payment->amount) }}</td>
                        <td>{{ optional($payment->paid_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="card">
    <h2 style="margin-top:0;font-size:1.1rem">Detalle</h2>
    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Venta</th>
                <th>Total</th>
                <th>COGS</th>
                <th>Margen / carrier</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($settlement->items as $item)
                <tr>
                    <td>{{ $item->item_type === 'return' ? 'Devolución' : 'Venta' }}</td>
                    <td>
                        <a href="{{ route('sales.sales.show', $item->sale) }}">{{ $item->sale?->number }}</a>
                        <div class="muted">{{ $item->sale?->customer?->name }}</div>
                    </td>
                    <td>{{ money($item->sale_total) }}</td>
                    <td>{{ money($item->cogs_total) }}</td>
                    <td>
                        @if ($item->item_type === 'return')
                            Carrier {{ money($item->carrier_cost_total) }}
                        @else
                            {{ money($item->real_margin_with_vat) }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
