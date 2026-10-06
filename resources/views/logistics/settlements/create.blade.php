@extends('layouts.app')

@section('title', 'Nueva liquidación logística')

@section('content')
<div class="topbar">
    <div>
        <h1>Liquidar empresa logística</h1>
        <p class="muted">Envíos <strong>entregados</strong> (pagan COD) y <strong>devoluciones</strong> (se descuenta el flete) · semanal o mensual</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('logistics.settlements.index') }}">Historial</a>
</div>

@if ($errors->any())
    <div class="flash" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-bottom:1rem">
        {{ $errors->first() }}
    </div>
@endif
@if ($error)
    <div class="flash" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-bottom:1rem">{{ $error }}</div>
@endif

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('logistics.settlements.create') }}" style="flex-wrap:wrap;align-items:end">
        <div class="field" style="margin:0">
            <label for="logistics_client_id">Empresa *</label>
            <select id="logistics_client_id" name="logistics_client_id" required>
                <option value="">—</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected((int) $clientId === (int) $client->id)>
                        {{ $client->code }} — {{ $client->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin:0">
            <label for="period_type">Período *</label>
            <select id="period_type" name="period_type" required>
                <option value="week" @selected($periodType === 'week')>Semanal</option>
                <option value="month" @selected($periodType === 'month')>Mensual</option>
                <option value="custom" @selected($periodType === 'custom')>Personalizado</option>
            </select>
        </div>
        <div class="field" style="margin:0" id="anchor-field">
            <label for="anchor_date">Fecha de referencia</label>
            <input id="anchor_date" type="date" name="anchor_date" value="{{ $anchorDate }}">
        </div>
        <div class="field" style="margin:0;display:none" id="from-field">
            <label for="from">Desde</label>
            <input id="from" type="date" name="from" value="{{ $from }}">
        </div>
        <div class="field" style="margin:0;display:none" id="to-field">
            <label for="to">Hasta</label>
            <input id="to" type="date" name="to" value="{{ $to }}">
        </div>
        <button class="btn" type="submit">Calcular</button>
    </form>
</div>

@if ($preview)
    <div class="meta" style="margin-bottom:1rem;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
        <div class="card">Empresa<strong>{{ $preview['client']->name }}</strong></div>
        <div class="card">Período<strong>{{ $preview['period_from']->format('d/m/Y') }} – {{ $preview['period_to']->format('d/m/Y') }}</strong></div>
        <div class="card">Entregados<strong>{{ $preview['shipments_count'] }}</strong></div>
        <div class="card">Devoluciones<strong>{{ $preview['returns_count'] }}</strong></div>
        @if ($preview['amount_due'] < 0)
            <div class="card">La empresa te debe<strong style="color:#991b1b">{{ money(abs($preview['amount_due'])) }}</strong></div>
        @else
            <div class="card">A pagar<strong style="color:var(--signal)">{{ money($preview['amount_due']) }}</strong></div>
        @endif
    </div>

    <div class="card" style="margin-bottom:1rem">
        <p class="muted" style="margin-top:0">{{ $preview['formula'] }}</p>
        <p>COD entregados: {{ money($preview['collect_total']) }}</p>
        <p>− Comisión Sistrack: {{ money($preview['carrier_commission_total']) }}</p>
        <p>− Flete entregados: {{ money($preview['delivered_shipping_total']) }}</p>
        <p>− Tu comisión: {{ money($preview['service_commission_total']) }}</p>
        <p>= Entregados: {{ money($preview['delivered_due']) }}</p>
        <p>− Flete perdido en devoluciones ({{ $preview['returns_count'] }}): {{ money($preview['return_shipping_total']) }}</p>
        <p><strong>= {{ $preview['amount_due'] < 0 ? 'La empresa te debe '.money(abs($preview['amount_due'])) : 'A pagar '.money($preview['amount_due']) }}</strong></p>
    </div>

    @if ($preview['shipments']->isNotEmpty())
        <div class="card" style="margin-bottom:1rem">
            <h2 style="margin-top:0;font-size:1.05rem">Envíos entregados</h2>
            <table>
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Destinatario</th>
                        <th>COD</th>
                        <th>Sistrack</th>
                        <th>Flete</th>
                        <th>Comisión</th>
                        <th>A pagar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($preview['shipments'] as $shipment)
                        <tr>
                            <td>{{ $shipment->number }}</td>
                            <td>{{ $shipment->recipient_name }}</td>
                            <td>{{ money($shipment->collect_amount) }}</td>
                            <td>{{ money($shipment->carrier_commission_amount) }}</td>
                            <td>{{ money($shipment->carrier_shipping_cost) }}</td>
                            <td>{{ money($shipment->service_commission) }}</td>
                            <td>{{ money($shipment->payable_to_client) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($preview['returns']->isNotEmpty())
        <div class="card" style="margin-bottom:1rem">
            <h2 style="margin-top:0;font-size:1.05rem">Devoluciones (no pagan COD · se descuenta el flete)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Destinatario</th>
                        <th>COD original</th>
                        <th>Flete perdido</th>
                        <th>A pagar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($preview['returns'] as $shipment)
                        <tr>
                            <td>{{ $shipment->number }}</td>
                            <td>{{ $shipment->recipient_name }}</td>
                            <td class="muted">{{ money($shipment->collect_amount) }}</td>
                            <td>{{ money($shipment->carrier_shipping_cost) }}</td>
                            <td style="color:#991b1b">−{{ money($shipment->carrier_shipping_cost) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('logistics.settlements.store') }}">
            @csrf
            <input type="hidden" name="logistics_client_id" value="{{ $preview['client']->id }}">
            <input type="hidden" name="period_type" value="{{ $preview['period_type'] }}">
            <input type="hidden" name="period_from" value="{{ $preview['period_from']->toDateString() }}">
            <input type="hidden" name="period_to" value="{{ $preview['period_to']->toDateString() }}">

            <div class="grid-2">
                <div class="field">
                    <label for="paid_at">Fecha de pago</label>
                    <input id="paid_at" type="datetime-local" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d\\TH:i')) }}">
                </div>
                <div class="field">
                    <label for="payment_method">Método</label>
                    <input id="payment_method" type="text" name="payment_method" value="{{ old('payment_method') }}" placeholder="Transferencia, efectivo…">
                </div>
            </div>
            <div class="field">
                <label for="notes">Notas</label>
                <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
            </div>
            <button class="btn" type="submit" @disabled($preview['shipments_count'] === 0 && $preview['returns_count'] === 0)>
                Confirmar liquidación · {{ $preview['amount_due'] < 0 ? 'te deben '.money(abs($preview['amount_due'])) : money($preview['amount_due']) }}
            </button>
        </form>
    </div>
@endif

<script>
(() => {
    const period = document.getElementById('period_type');
    const anchor = document.getElementById('anchor-field');
    const from = document.getElementById('from-field');
    const to = document.getElementById('to-field');
    const toggle = () => {
        const custom = period.value === 'custom';
        anchor.style.display = custom ? 'none' : '';
        from.style.display = custom ? '' : 'none';
        to.style.display = custom ? '' : 'none';
    };
    period?.addEventListener('change', toggle);
    toggle();
})();
</script>
@endsection
