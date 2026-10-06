@extends('layouts.app')

@section('title', 'Caja '.$session->number)

@section('content')
@php
    $methodLabels = config('sales.payment_methods');
    $diff = (float) $session->difference;
@endphp
<div class="topbar">
    <div>
        <h1>Caja {{ $session->number }}@if ($session->store) · {{ $session->store->name }}@endif</h1>
        <p class="muted">
            @if ($session->isOpen())
                <span class="badge badge-ok">Abierta</span>
            @else
                <span class="badge badge-off">Cerrada</span>
            @endif
            Apertura {{ $session->opened_at->format('d/m/Y H:i') }}{{ $session->opened_by ? ' por '.$session->opened_by : '' }}
            @if ($session->cashier) · Cajero: <strong>{{ $session->cashier->name }}</strong>@endif
            @if ($session->closed_at)
                · Cierre {{ $session->closed_at->format('d/m/Y H:i') }}{{ $session->closed_by ? ' por '.$session->closed_by : '' }}
            @endif
        </p>
    </div>
    <div class="actions">
        @if ($session->isOpen())
            <a class="btn" href="{{ route('store.pos') }}">Punto de venta</a>
        @endif
        <a class="btn btn-secondary" href="{{ route('store.cash.report', $session) }}" target="_blank">Imprimir corte</a>
        <a class="btn btn-secondary" href="{{ route('store.cash.index') }}">Historial</a>
    </div>
</div>

<div class="grid-2" style="align-items:start;margin-bottom:1rem">
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Resumen</h2>
        <table>
            <tbody>
                <tr><td>Fondo inicial</td><td style="text-align:right">{{ money($session->opening_amount) }}</td></tr>
                @foreach (['cash', 'card', 'transfer', 'other'] as $method)
                    @continue($method === 'other' && $summary['by_method']['other'] <= 0)
                    <tr>
                        <td>Ventas {{ strtolower($methodLabels[$method] ?? 'otros') }}</td>
                        <td style="text-align:right">{{ money($summary['by_method'][$method]) }}</td>
                    </tr>
                @endforeach
                <tr><td>Entradas de efectivo</td><td style="text-align:right">+{{ money($summary['cash_in_total']) }}</td></tr>
                <tr><td>Salidas de efectivo</td><td style="text-align:right">−{{ money($summary['cash_out_total']) }}</td></tr>
                <tr><td><strong>Total vendido</strong> ({{ $summary['sales_count'] }})</td><td style="text-align:right"><strong>{{ money($summary['sales_total']) }}</strong></td></tr>
                <tr><td><strong>Efectivo esperado en caja</strong></td><td style="text-align:right"><strong>{{ money($summary['expected_cash']) }}</strong></td></tr>
                @unless ($session->isOpen())
                    <tr><td>Efectivo contado</td><td style="text-align:right">{{ money($session->counted_cash) }}</td></tr>
                    <tr>
                        <td><strong>Diferencia</strong></td>
                        <td style="text-align:right">
                            @if (abs($diff) < 0.01)
                                <span class="badge badge-ok">Cuadrada</span>
                            @else
                                <span class="badge {{ $diff > 0 ? 'badge-warn' : 'badge-off' }}">{{ $diff > 0 ? 'Sobrante +' : 'Faltante −' }}{{ money(abs($diff)) }}</span>
                            @endif
                        </td>
                    </tr>
                @endunless
            </tbody>
        </table>
        <p class="muted" style="font-size:.85rem;margin-bottom:0">Efectivo esperado = fondo inicial + ventas en efectivo + entradas − salidas. Tarjeta y transferencia no entran a la gaveta.</p>
        @if ($session->closing_notes)
            <p style="margin-bottom:0"><strong>Nota de cierre:</strong> {{ $session->closing_notes }}</p>
        @endif
    </div>

    @if ($session->isOpen())
        <div style="display:grid;gap:1rem">
            <div class="card" id="cajero">
                <h2 style="margin-top:0;font-size:1.1rem">Cajero</h2>
                <form method="POST" action="{{ route('store.cash.cashier', $session) }}" style="display:flex;gap:.6rem;align-items:end;flex-wrap:wrap">
                    @csrf
                    @method('PATCH')
                    <div class="field" style="margin:0;flex:1;min-width:200px">
                        <label for="cashier_id">Vendedor a cargo de la caja</label>
                        <select id="cashier_id" name="cashier_id">
                            <option value="">— Sin cajero —</option>
                            @foreach ($sellers as $seller)
                                <option value="{{ $seller->id }}" @selected($session->cashier_id === $seller->id)>{{ $seller->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-secondary" type="submit">Cambiar cajero</button>
                </form>
                <p class="muted" style="font-size:.85rem;margin:.5rem 0 0">Las ventas nuevas saldrán a nombre del cajero por defecto; en el punto de venta se puede elegir otro vendedor.</p>
            </div>

            <div class="card">
                <h2 style="margin-top:0;font-size:1.1rem">Entrada / salida de efectivo</h2>
                <form method="POST" action="{{ route('store.cash.movements.store', $session) }}">
                    @csrf
                    <div class="grid-2" style="gap:.6rem">
                        <div class="field" style="margin:0">
                            <label for="type">Tipo</label>
                            <select id="type" name="type" data-no-search>
                                <option value="out">Salida (pago, retiro)</option>
                                <option value="in">Entrada (cambio, depósito)</option>
                            </select>
                        </div>
                        <div class="field" style="margin:0">
                            <label for="amount">Monto (USD)</label>
                            <input id="amount" type="number" step="0.01" min="0.01" name="amount" required>
                        </div>
                    </div>
                    <div class="field" style="margin-top:.6rem">
                        <label for="reason">Motivo *</label>
                        <input id="reason" type="text" name="reason" maxlength="255" required placeholder="Ej. compra de bolsas, retiro para depósito">
                    </div>
                    <button class="btn btn-secondary" type="submit">Registrar movimiento</button>
                </form>
            </div>

            <div class="card">
                <h2 style="margin-top:0;font-size:1.1rem">Cerrar caja (corte)</h2>
                <form method="POST" action="{{ route('store.cash.close', $session) }}" onsubmit="return confirm('¿Cerrar la caja? Ya no se podrán registrar ventas en ella.')">
                    @csrf
                    <div class="field">
                        <label for="counted_cash">Efectivo contado en gaveta (USD) *</label>
                        <input id="counted_cash" type="number" step="0.01" min="0" name="counted_cash" value="{{ old('counted_cash') }}" required>
                        <small class="muted">Esperado: {{ money($summary['expected_cash']) }} · <span id="close-diff"></span></small>
                    </div>
                    <div class="field">
                        <label for="closed_by_member_id">Cerrado por *</label>
                        @php $closerDefault = (int) old('closed_by_member_id', $team->contains('id', auth()->id()) ? auth()->id() : $team->firstWhere('seller_id', $session->cashier_id)?->id); @endphp
                        <select id="closed_by_member_id" name="closed_by_member_id" required>
                            <option value="">— Selecciona —</option>
                            @foreach ($team as $member)
                                <option value="{{ $member->id }}" @selected($closerDefault === $member->id)>
                                    {{ $member->name }}@if ($session->cashier_id && $member->seller_id === $session->cashier_id) (cajero de esta caja)@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="closing_notes">Nota</label>
                        <input id="closing_notes" type="text" name="closing_notes" value="{{ old('closing_notes') }}" maxlength="1000">
                    </div>
                    <button class="btn btn-danger" type="submit">Cerrar caja</button>
                </form>
                <script>
                (() => {
                    const expected = {{ json_encode((float) $summary['expected_cash']) }};
                    const input = document.getElementById('counted_cash');
                    const out = document.getElementById('close-diff');
                    const update = () => {
                        if (input.value === '') { out.textContent = ''; return; }
                        const d = Math.round((parseFloat(input.value) - expected) * 100) / 100;
                        out.textContent = Math.abs(d) < 0.01 ? 'Cuadrada' : (d > 0 ? `Sobrante $${d.toFixed(2)}` : `Faltante $${Math.abs(d).toFixed(2)}`);
                        out.style.color = Math.abs(d) < 0.01 ? 'var(--ok)' : 'var(--danger)';
                    };
                    input.addEventListener('input', update);
                    update();
                })();
                </script>
            </div>
        </div>
    @else
        <div class="card">
            <h2 style="margin-top:0;font-size:1.1rem">Movimientos de efectivo</h2>
            @include('store.cash._movements', ['movements' => $session->movements])
        </div>
    @endif
</div>

@if ($session->isOpen())
    <div class="card" style="margin-bottom:1rem">
        <h2 style="margin-top:0;font-size:1.1rem">Movimientos de efectivo</h2>
        @include('store.cash._movements', ['movements' => $session->movements])
    </div>
@endif

<div class="card">
    <h2 style="margin-top:0;font-size:1.1rem">Ventas de esta caja</h2>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Venta</th>
                    <th>Hora</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Pago</th>
                    <th style="text-align:right">Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($session->sales as $sale)
                    <tr @if ($sale->status === \App\Models\Sale::STATUS_VOIDED) style="opacity:.5;text-decoration:line-through" @endif>
                        <td><a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a></td>
                        <td>{{ $sale->sold_at?->format('H:i') }}</td>
                        <td>{{ $sale->customer?->name }}</td>
                        <td>{{ $sale->seller?->name }}</td>
                        <td>{{ $methodLabels[$sale->payment_method] ?? $sale->payment_method }}</td>
                        <td style="text-align:right">{{ money($sale->total) }}</td>
                        <td style="text-align:right"><a href="{{ route('store.sales.ticket', $sale) }}" target="_blank">Ticket</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Sin ventas en esta caja.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
