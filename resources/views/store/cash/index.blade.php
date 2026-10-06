@extends('layouts.app')

@section('title', 'Caja')

@section('content')
<div class="topbar">
    <div>
        <h1>Caja</h1>
        <p class="muted">
            Apertura, movimientos y corte por tienda.
            @if ($deviceStore) Este equipo: <strong>{{ $deviceStore->name }}</strong> · <a href="{{ route('store.select', ['redirect' => 'cash']) }}">cambiar</a>@endif
        </p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('store.pos') }}">Punto de venta</a>
    </div>
</div>

@if ($panels->isEmpty())
    <div class="card" style="margin-bottom:1rem">
        <p style="margin-top:0">No hay tiendas activas.</p>
        <a class="btn" href="{{ route('settings.stores.create') }}">Crear tienda</a>
    </div>
@endif

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1rem;margin-bottom:1rem">
    @foreach ($panels as $panel)
        @php($store = $panel['store'])
        @php($current = $panel['session'])
        <div class="card" style="margin:0">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;margin-bottom:.75rem">
                <strong style="font-size:1.05rem">{{ $store->name }}</strong>
                <span class="badge {{ $current ? 'badge-ok' : 'badge-off' }}">{{ $current ? 'Abierta' : 'Cerrada' }}</span>
            </div>

            @if ($current)
                <p class="muted" style="margin:0 0 .75rem">{{ $current->number }} · desde {{ $current->opened_at->format('d/m H:i') }}{{ $current->opened_by ? ' · '.$current->opened_by : '' }}</p>
                @if ($current->cashier)<p style="margin:0 0 .75rem">Cajero: <strong>{{ $current->cashier->name }}</strong></p>@endif
                <table>
                    <tbody>
                        <tr><td>Fondo inicial</td><td style="text-align:right">{{ money($current->opening_amount) }}</td></tr>
                        <tr><td>Ventas ({{ $panel['summary']['sales_count'] }})</td><td style="text-align:right">{{ money($panel['summary']['sales_total']) }}</td></tr>
                        <tr><td>En efectivo</td><td style="text-align:right">{{ money($panel['summary']['by_method']['cash']) }}</td></tr>
                        <tr><td><strong>Efectivo esperado</strong></td><td style="text-align:right"><strong>{{ money($panel['summary']['expected_cash']) }}</strong></td></tr>
                    </tbody>
                </table>
                <a class="btn btn-secondary" href="{{ route('store.cash.show', $current) }}" style="margin-top:.75rem">Ver caja / hacer corte</a>
            @else
                <form method="POST" action="{{ route('store.cash.open') }}">
                    @csrf
                    <input type="hidden" name="store_id" value="{{ $store->id }}">
                    <div class="grid-2" style="gap:.6rem">
                        <div class="field" style="margin:0">
                            <label>Fondo inicial (USD) *</label>
                            <input type="number" step="0.01" min="0" name="opening_amount" value="0.00" required>
                        </div>
                        <div class="field" style="margin:0">
                            <label>Cajero</label>
                            <select name="cashier_id">
                                <option value="">— Sin cajero —</option>
                                @foreach ($sellers as $seller)
                                    <option value="{{ $seller->id }}" @selected(auth()->user()->seller_id === $seller->id)>{{ $seller->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button class="btn" type="submit" style="margin-top:.75rem">Abrir caja</button>
                </form>
            @endif
        </div>
    @endforeach
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:.75rem">
        <h2 style="margin:0;font-size:1.1rem">Historial de cortes</h2>
        @if ($allStores->count() > 1)
            <form method="GET" action="{{ route('store.cash.index') }}" class="search" style="margin:0">
                <select name="store_id" data-no-search onchange="this.form.submit()">
                    <option value="">Todas las tiendas</option>
                    @foreach ($allStores as $s)
                        <option value="{{ $s->id }}" @selected((string) request('store_id') === (string) $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Caja</th>
                    <th>Tienda</th>
                    <th>Apertura</th>
                    <th>Cierre</th>
                    <th style="text-align:right">Ventas</th>
                    <th style="text-align:right">Esperado</th>
                    <th style="text-align:right">Contado</th>
                    <th style="text-align:right">Diferencia</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $s)
                    @php($diff = (float) $s->difference)
                    <tr>
                        <td>
                            <strong>{{ $s->number }}</strong>
                            @if ($s->isOpen())<span class="badge badge-ok">Abierta</span>@endif
                        </td>
                        <td>{{ $s->store?->name ?? '—' }}</td>
                        <td>{{ $s->opened_at->format('d/m/Y H:i') }}<div class="muted" style="font-size:.8rem">{{ $s->opened_by }}</div></td>
                        <td>{{ $s->closed_at?->format('d/m/Y H:i') ?? '—' }}<div class="muted" style="font-size:.8rem">{{ $s->closed_by }}</div></td>
                        <td style="text-align:right">{{ $s->isOpen() ? '—' : $s->sales_count.' · '.money($s->sales_total) }}</td>
                        <td style="text-align:right">{{ $s->isOpen() ? '—' : money($s->expected_cash) }}</td>
                        <td style="text-align:right">{{ $s->isOpen() ? '—' : money($s->counted_cash) }}</td>
                        <td style="text-align:right">
                            @if ($s->isOpen())
                                —
                            @elseif (abs($diff) < 0.01)
                                <span class="badge badge-ok">Cuadrada</span>
                            @else
                                <span class="badge {{ $diff > 0 ? 'badge-warn' : 'badge-off' }}">{{ $diff > 0 ? '+' : '−' }}{{ money(abs($diff)) }}</span>
                            @endif
                        </td>
                        <td style="text-align:right"><a href="{{ route('store.cash.show', $s) }}">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Todavía no hay cajas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $sessions->links() }}
</div>
@endsection
