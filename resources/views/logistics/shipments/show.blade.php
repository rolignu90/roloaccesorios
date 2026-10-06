@extends('layouts.app')

@section('title', $shipment->number)

@section('content')
@php
    $user = auth()->user();
    $canCreate = $user->can('logistics.create');
    $canVoidPerm = $user->can('logistics.void');
    $sistrackActions = $canCreate && ($shipment->canResendToSistrack() || $shipment->canSyncSistrackStatus());
    $statusActions = ($canCreate && $shipment->canMarkDelivered()) || ($canVoidPerm && $shipment->canMarkReturned());
    $voidAction = $canVoidPerm && $shipment->canVoid();
@endphp
<div class="topbar">
    <div>
        <a class="muted" href="{{ route('logistics.shipments.index') }}" style="font-size:.88rem;text-decoration:none">← Envíos</a>
        <h1>{{ $shipment->number }}</h1>
        <p class="muted">
            {{ $shipment->client?->code }} — {{ $shipment->client?->name }}
            · <span class="badge {{ $shipment->statusBadgeClass() }}">{{ $shipment->statusLabel() }}</span>
        </p>
    </div>
    <div class="actions">
        @if ($shipment->hasSistrackLabel())
            @php $labelSize = request()->cookie('sistrack_label_size', \App\Services\SistrackClient::LABEL_DEFAULT_SIZE); @endphp
            <form method="GET" action="{{ route('logistics.shipments.sistrack-label', $shipment) }}" target="_blank" style="display:flex;gap:.4rem;align-items:center">
                <select name="size" data-no-search aria-label="Tamaño de etiqueta" style="width:auto">
                    @foreach (\App\Services\SistrackClient::LABEL_SIZES as $value => $label)
                        <option value="{{ $value }}" @selected($labelSize === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn" type="submit">Etiqueta Sistrack</button>
            </form>
        @endif
        @if ($canCreate && $shipment->canSendToSistrack())
            <form method="POST" action="{{ route('logistics.shipments.send-sistrack.one', $shipment) }}">
                @csrf
                <button class="btn" type="submit">Enviar a Sistrack</button>
            </form>
        @endif
        @if ($canCreate && $shipment->canEdit())
            <a class="btn btn-secondary" href="{{ route('logistics.shipments.edit', $shipment) }}">Editar</a>
        @endif

        @if ($sistrackActions || $statusActions || $voidAction)
            <details class="action-menu">
                <summary class="btn btn-secondary">Más acciones ▾</summary>
                <div class="action-menu-panel">
                    @if ($sistrackActions)
                        <div class="action-menu-label">Sistrack</div>
                        @if ($shipment->canSyncSistrackStatus())
                            <form method="POST" action="{{ route('logistics.shipments.sync-sistrack-status.one', $shipment) }}">
                                @csrf
                                <button type="submit">Sincronizar estado</button>
                            </form>
                        @endif
                        @if ($shipment->canResendToSistrack())
                            <form method="POST" action="{{ route('logistics.shipments.resend-sistrack.one', $shipment) }}" onsubmit="return confirm('¿Reenviar a Sistrack?\n\nSe crea una orden NUEVA en Sistrack (guía nueva).')">
                                @csrf
                                <button type="submit">Reenviar (guía nueva)</button>
                            </form>
                        @endif
                    @endif

                    @if ($statusActions)
                        @if ($sistrackActions)<div class="action-menu-sep"></div>@endif
                        <div class="action-menu-label">Estado</div>
                        @if ($canCreate && $shipment->canMarkDelivered())
                            <form method="POST" action="{{ route('logistics.shipments.mark-delivered.one', $shipment) }}">
                                @csrf
                                <button type="submit">Marcar entregado</button>
                            </form>
                        @endif
                        @if ($canVoidPerm && $shipment->canMarkReturned())
                            <form method="POST" action="{{ route('logistics.shipments.mark-returned.one', $shipment) }}" onsubmit="return confirm('¿Marcar devolución?\n\nDevolución = el paquete salió y regresó sin entregarse: no se devuelve COD y el flete de retorno se cobra a la empresa en la liquidación.\n\nSi solo quieres cancelar el pedido, usa «Cancelar envío».')">
                                @csrf
                                <button type="submit" title="El paquete salió y regresó sin entregarse">Marcar devolución</button>
                            </form>
                        @endif
                    @endif

                    @if ($voidAction)
                        @php
                            $voidMsg = '¿Cancelar este envío?\n\nQueda anulado: no cuenta en liquidaciones ni se cobra flete.';
                            if ($shipment->hasSistrackLabel()) {
                                $voidMsg .= '\n\nOJO: ya está en Sistrack. Esto no cancela la guía allá; cancélala también en Sistrack.';
                            }
                        @endphp
                        @if ($sistrackActions || $statusActions)<div class="action-menu-sep"></div>@endif
                        <form method="POST" action="{{ route('logistics.shipments.void', $shipment) }}" onsubmit="return confirm('{{ $voidMsg }}')">
                            @csrf
                            <button class="danger" type="submit" title="El pedido no se envía (error o cancelado por la empresa)">Cancelar envío</button>
                        </form>
                    @endif
                </div>
            </details>
        @endif
    </div>
</div>

<div class="meta" style="margin-bottom:1rem;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
    <div class="card">COD<strong>{{ money($shipment->collect_amount) }}</strong></div>
    <div class="card">Comisión Sistrack<strong>{{ money($shipment->carrier_commission_amount) }}</strong></div>
    <div class="card">Flete<strong>{{ money($shipment->carrier_shipping_cost) }}</strong></div>
    <div class="card">Tu comisión<strong>{{ money($shipment->service_commission) }}</strong></div>
    <div class="card">A devolver<strong style="color:var(--signal)">{{ money($shipment->payable_to_client) }}</strong></div>
</div>

<div class="grid-2" style="margin-bottom:1rem">
    <div class="card">
        <h2 style="margin-top:0;font-size:1.05rem">Destinatario</h2>
        <p><strong>{{ $shipment->recipient_name }}</strong></p>
        <p>{{ $shipment->recipient_phone ?: '—' }}</p>
        <p>{{ $shipment->recipient_address }}</p>
        <p class="muted">{{ $shipment->municipality }}, {{ $shipment->department }}</p>
        <p style="margin-top:1rem"><strong>Producto:</strong> {{ $shipment->description }}</p>
        @if ($shipment->notes)
            <p class="muted">{{ $shipment->notes }}</p>
        @endif
    </div>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.05rem">Sistrack / courier</h2>
        <p>Courier: <strong>{{ $shipment->shippingCarrier?->name }}</strong></p>
        <p>Estado Sistrack: <strong>{{ $shipment->sistrack_status }}</strong></p>
        <p>Shipping status: {{ $shipment->sistrack_shipping_status ?: '—' }}</p>
        <p>External ID: {{ $shipment->sistrack_external_id ?: '—' }}</p>
        @if ($shipment->sistrack_last_error)
            <p style="color:#991b1b">{{ $shipment->sistrack_last_error }}</p>
        @endif
        <p class="muted" style="margin-top:1rem">
            Fecha: {{ optional($shipment->shipped_at)->format('d/m/Y H:i') }}
            @if ($shipment->status_changed_at)
                · Estado desde {{ $shipment->status_changed_at->format('d/m/Y H:i') }}
            @endif
            @if ($shipment->createdBy)
                <br>Creado por {{ $shipment->createdBy->name }}
            @endif
            @if ($shipment->voidedBy)
                · Anulado por {{ $shipment->voidedBy->name }}
            @endif
        </p>
    </div>
</div>

@if ($shipment->settlementItems->isNotEmpty())
    <div class="card">
        <h2 style="margin-top:0;font-size:1.05rem">Liquidaciones</h2>
        <ul>
            @foreach ($shipment->settlementItems as $item)
                <li>
                    @can('logistics.settlements')
                        <a href="{{ route('logistics.settlements.show', $item->settlement) }}">{{ $item->settlement?->number }}</a>
                    @else
                        {{ $item->settlement?->number }}
                    @endcan
                    · {{ $item->item_type }}
                    · {{ money($item->payable_to_client) }}
                </li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
