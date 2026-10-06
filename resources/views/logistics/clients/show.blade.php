@extends('layouts.app')

@section('title', $client->name)

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $client->name }}</h1>
        <p class="muted">{{ $client->code }} · {{ $client->commissionSummary() }}</p>
    </div>
    <div class="actions">
        @can('logistics.create')
            <a class="btn" href="{{ route('logistics.shipments.create', ['client_id' => $client->id]) }}">Nuevo envío</a>
        @endcan
        @can('logistics.settlements')
            <a class="btn btn-secondary" href="{{ route('logistics.settlements.create', ['logistics_client_id' => $client->id]) }}">Liquidar</a>
        @endcan
        @can('logistics.clients')
            <a class="btn btn-secondary" href="{{ route('logistics.clients.edit', $client) }}">Editar</a>
        @endcan
    </div>
</div>

<div class="meta" style="margin-bottom:1rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
    <div class="card">Teléfono<strong>{{ $client->phone ?: '—' }}</strong></div>
    <div class="card">Email<strong>{{ $client->email ?: '—' }}</strong></div>
    <div class="card">Courier<strong>{{ $client->defaultShippingCarrier?->name ?? '—' }}</strong></div>
    <div class="card">Estado<strong>{{ $client->is_active ? 'Activo' : 'Inactivo' }}</strong></div>
</div>

@if ($client->notes)
    <div class="card" style="margin-bottom:1rem"><p style="margin:0">{{ $client->notes }}</p></div>
@endif

<div class="card">
    <h2 style="margin-top:0;font-size:1.05rem">Últimos envíos</h2>
    <table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Fecha</th>
                <th>Destinatario</th>
                <th>COD</th>
                <th>A pagarles</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recentShipments as $shipment)
                <tr>
                    <td><a href="{{ route('logistics.shipments.show', $shipment) }}">{{ $shipment->number }}</a></td>
                    <td>{{ optional($shipment->shipped_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ $shipment->recipient_name }}</td>
                    <td>{{ money($shipment->collect_amount) }}</td>
                    <td>{{ money($shipment->payable_to_client) }}</td>
                    <td><span class="badge {{ $shipment->statusBadgeClass() }}">{{ $shipment->statusLabel() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Sin envíos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
