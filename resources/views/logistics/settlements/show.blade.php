@extends('layouts.app')

@section('title', $settlement->number)

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $settlement->number }}</h1>
        <p class="muted">
            {{ $settlement->client?->code }} — {{ $settlement->client?->name }}
            · {{ $settlement->periodLabel() }}
            · {{ $settlement->period_from->format('d/m/Y') }} – {{ $settlement->period_to->format('d/m/Y') }}
            @if ($settlement->createdBy) · Registrada por {{ $settlement->createdBy->name }}@endif
            @if ($settlement->voidedBy) · Anulada por {{ $settlement->voidedBy->name }}@endif
        </p>
    </div>
    <div class="actions">
        @if (! $settlement->isVoided())
            <form method="POST" action="{{ route('logistics.settlements.void', $settlement) }}" onsubmit="return confirm('¿Anular liquidación? Los envíos podrán liquidarse de nuevo.')">
                @csrf
                <button class="btn btn-secondary" type="submit">Anular</button>
            </form>
        @endif
        <a class="btn btn-secondary" href="{{ route('logistics.settlements.index') }}">Historial</a>
    </div>
</div>

<div class="meta" style="margin-bottom:1rem;grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
    <div class="card">Entregados<strong>{{ $settlement->shipments_count }}</strong></div>
    <div class="card">Devoluciones<strong>{{ $settlement->returns_count }}</strong></div>
    <div class="card">COD<strong>{{ money($settlement->collect_total) }}</strong></div>
    <div class="card">Sistrack<strong>{{ money($settlement->carrier_commission_total) }}</strong></div>
    <div class="card">Flete<strong>{{ money($settlement->carrier_shipping_total) }}</strong></div>
    <div class="card">Tu comisión<strong>{{ money($settlement->service_commission_total) }}</strong></div>
    @if ((float) $settlement->amount_due < 0)
        <div class="card">La empresa te debe<strong style="color:#991b1b">{{ money(abs((float) $settlement->amount_due)) }}</strong></div>
    @else
        <div class="card">Pagado a empresa<strong style="color:var(--signal)">{{ money($settlement->amount_due) }}</strong></div>
    @endif
</div>

<div class="card" style="margin-bottom:1rem">
    <p>
        Estado:
        <span class="badge {{ $settlement->isVoided() ? 'badge-off' : 'badge-ok' }}">
            {{ $settlement->isVoided() ? 'Anulada' : 'Pagada' }}
        </span>
        @if ($settlement->paid_at)
            · {{ $settlement->paid_at->format('d/m/Y H:i') }}
        @endif
        @if ($settlement->payment_method)
            · {{ $settlement->payment_method }}
        @endif
    </p>
    @if ($settlement->notes)
        <p class="muted">{{ $settlement->notes }}</p>
    @endif
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Envío</th>
                <th>Destinatario</th>
                <th>COD</th>
                <th>Sistrack</th>
                <th>Flete</th>
                <th>Comisión</th>
                <th>A pagar</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($settlement->items as $item)
                <tr>
                    <td>{{ $item->item_type === 'return' ? 'Devolución' : 'Entrega' }}</td>
                    <td>
                        <a href="{{ route('logistics.shipments.show', $item->shipment) }}">
                            {{ $item->shipment?->number }}
                        </a>
                    </td>
                    <td>{{ $item->shipment?->recipient_name }}</td>
                    <td>{{ money($item->collect_amount) }}</td>
                    <td>{{ money($item->carrier_commission_amount) }}</td>
                    <td>{{ money($item->carrier_shipping_cost) }}</td>
                    <td>{{ money($item->service_commission) }}</td>
                    @if ((float) $item->payable_to_client < 0)
                        <td style="color:#991b1b">−{{ money(abs((float) $item->payable_to_client)) }}</td>
                    @else
                        <td>{{ money($item->payable_to_client) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
