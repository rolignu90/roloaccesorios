@extends('layouts.app')

@section('title', 'Envíos terceros')

@section('content')
<div class="topbar">
    <div>
        <h1>Envíos a terceros</h1>
        <p class="muted">Sin inventario ROLO · COD − Sistrack − flete − tu comisión</p>
    </div>
    @can('logistics.create')
        <div class="actions">
            <form method="POST" action="{{ route('logistics.shipments.sync-sistrack-status') }}" style="display:inline">
                @csrf
                <button class="btn btn-secondary" type="submit">Sync pendientes Sistrack</button>
            </form>
            <a class="btn" href="{{ route('logistics.shipments.create') }}">Nuevo envío</a>
        </div>
    @endcan
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('logistics.shipments.index') }}" style="flex-wrap:wrap">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Número, destinatario, teléfono…">
        <select name="client_id">
            <option value="">Todas las empresas</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected((string) request('client_id') === (string) $client->id)>
                    {{ $client->code }} — {{ $client->name }}
                </option>
            @endforeach
        </select>
        <select name="status">
            <option value="">Todos los estados</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="settled" data-no-search>
            <option value="">Liquidado: todos</option>
            <option value="1" @selected(request('settled') === '1')>Liquidados</option>
            <option value="0" @selected(request('settled') === '0')>Sin liquidar</option>
        </select>
        <label style="display:flex;align-items:center;gap:.35rem;font-size:.9rem">
            <input type="checkbox" name="pending_sync" value="1" @checked(request()->boolean('pending_sync'))>
            Solo sync pendiente
        </label>
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="card">
    <form method="POST" action="{{ route('logistics.shipments.send-sistrack') }}" id="bulk-sistrack">
        @csrf
        @php
            $labelSize = request()->cookie('sistrack_label_size', \App\Services\SistrackClient::LABEL_DEFAULT_SIZE);
            $canCreate = auth()->user()->can('logistics.create');
            $canSeeSettlements = auth()->user()->can('logistics.settlements');
        @endphp
        <div class="actions" style="margin-bottom:.75rem;flex-wrap:wrap;align-items:center">
            @if ($canCreate)
                <button class="btn btn-secondary" type="submit">Enviar seleccionados a Sistrack</button>
            @endif
            <span style="display:inline-flex;gap:.4rem;align-items:center">
                <select id="label-size" data-no-search aria-label="Tamaño de etiqueta" style="width:auto">
                    @foreach (\App\Services\SistrackClient::LABEL_SIZES as $value => $label)
                        <option value="{{ $value }}" @selected($labelSize === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary" type="button" id="print-labels">Imprimir etiquetas Sistrack</button>
            </span>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width:2rem"></th>
                    <th>Número</th>
                    <th>Empresa</th>
                    <th>Destinatario</th>
                    <th>COD</th>
                    <th>A pagarles</th>
                    <th>Estado</th>
                    <th style="text-align:center">Liquidado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shipments as $shipment)
                    <tr>
                        <td>
                            @if (($canCreate && $shipment->canSendToSistrack()) || $shipment->hasSistrackLabel())
                                <input type="checkbox" name="ids[]" value="{{ $shipment->id }}" data-has-label="{{ $shipment->hasSistrackLabel() ? 1 : 0 }}">
                            @endif
                        </td>
                        <td><a href="{{ route('logistics.shipments.show', $shipment) }}">{{ $shipment->number }}</a></td>
                        <td>{{ $shipment->client?->name }}</td>
                        <td>
                            {{ $shipment->recipient_name }}
                            @if ($shipment->recipient_phone)
                                <div class="muted" style="font-size:.82rem">{{ $shipment->recipient_phone }}</div>
                            @endif
                        </td>
                        <td>{{ money($shipment->collect_amount) }}</td>
                        <td>{{ money($shipment->payable_to_client) }}</td>
                        <td>
                            <span class="badge {{ $shipment->statusBadgeClass() }}">{{ $shipment->statusLabel() }}</span>
                            <div style="font-size:.78rem;margin-top:.25rem">
                                @if ($shipment->isSistrackSent())
                                    <span class="muted">Sistrack ✓</span>
                                @elseif ($shipment->sistrack_status === 'failed')
                                    <span style="color:#991b1b">Sistrack: falló</span>
                                @elseif (! $shipment->isVoided())
                                    <span class="muted">Sistrack: pendiente</span>
                                @endif
                            </div>
                        </td>
                        <td style="text-align:center">
                            @php $settlement = $shipment->activeSettlement(); @endphp
                            @if ($settlement)
                                <span class="badge badge-ok" title="Liquidado en {{ $settlement->number }}">✓ Sí</span>
                                <div style="font-size:.78rem;margin-top:.25rem">
                                    @if ($canSeeSettlements)
                                        <a href="{{ route('logistics.settlements.show', $settlement) }}">{{ $settlement->number }}</a>
                                    @else
                                        <span class="muted">{{ $settlement->number }}</span>
                                    @endif
                                </div>
                            @elseif ($shipment->isVoided())
                                <span class="muted">—</span>
                            @else
                                <span class="muted">No</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Sin envíos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </form>
    {{ $shipments->links() }}
</div>

<script>
(() => {
    document.getElementById('print-labels')?.addEventListener('click', () => {
        const picked = [...document.querySelectorAll('#bulk-sistrack input[name="ids[]"]:checked')];
        const withLabel = picked.filter((box) => box.dataset.hasLabel === '1');
        if (!withLabel.length) {
            alert(picked.length ? 'Los envíos seleccionados aún no están en Sistrack.' : 'Selecciona los envíos que quieres imprimir.');
            return;
        }
        if (withLabel.length > {{ \App\Services\SistrackClient::LABEL_MAX_PER_BATCH }}) {
            alert('Sistrack imprime máximo {{ \App\Services\SistrackClient::LABEL_MAX_PER_BATCH }} etiquetas por vez.');
            return;
        }
        const params = new URLSearchParams();
        withLabel.forEach((box) => params.append('ids[]', box.value));
        params.set('size', document.getElementById('label-size').value);
        window.open(@json(route('logistics.shipments.sistrack-labels')) + '?' + params.toString(), '_blank');
    });
})();
</script>
@endsection
