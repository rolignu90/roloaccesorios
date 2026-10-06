@extends('layouts.app')

@section('title', 'Vendidos por producto')

@section('content')
@php
    $maxUnits = max(1, (int) $rows->getCollection()->max('units_sold'));
    $periodLabel = $filters['period'] === 'custom'
        ? trim(($filters['from']?->format('d/m/Y') ?? '…').' – '.($filters['to']?->format('d/m/Y') ?? 'hoy'))
        : $periods[$filters['period']];
@endphp

<div class="topbar">
    <div>
        <h1>Vendidos por producto</h1>
        <p class="muted">{{ $periodLabel }} · unidades reales (los combos se cuentan por cada producto que incluyen) · sin anuladas</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('inventory.sold.export', request()->query()) }}">Exportar CSV</a>
</div>

<div class="card" style="margin-bottom:1rem">
    <div style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin-bottom:.75rem">
        <strong style="font-size:.9rem;margin-right:.25rem">Período:</strong>
        @foreach ($periods as $key => $label)
            @continue($key === 'custom')
            <a class="btn {{ $filters['period'] === $key ? '' : 'btn-secondary' }}" style="padding:.35rem .75rem;font-size:.88rem"
               href="{{ route('inventory.sold.index', array_merge(request()->except(['period', 'from', 'to', 'page']), ['period' => $key])) }}">{{ $label }}</a>
        @endforeach
        <button type="button" id="sold-range-toggle" class="btn {{ $filters['period'] === 'custom' ? '' : 'btn-secondary' }}" style="padding:.35rem .75rem;font-size:.88rem">Rango de fechas…</button>
    </div>
    <form class="search" method="GET" action="{{ route('inventory.sold.index') }}" style="flex-wrap:wrap;align-items:end">
        <input type="hidden" name="period" id="sold-period" value="{{ $filters['period'] }}">
        <span id="sold-range" style="display:{{ $filters['period'] === 'custom' ? 'inline-flex' : 'none' }};gap:.4rem;align-items:center">
            <label class="muted" style="font-size:.85rem">Desde <input type="date" name="from" value="{{ $filters['from_input'] }}"></label>
            <label class="muted" style="font-size:.85rem">Hasta <input type="date" name="to" value="{{ $filters['to_input'] }}"></label>
        </span>
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Código o nombre">
        <select name="channel" data-no-search>
            <option value="">Todos los canales</option>
            @foreach ($channels as $key => $label)
                <option value="{{ $key }}" @selected($filters['channel'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @if ($stores->count() > 1)
            <select name="store_id" data-no-search>
                <option value="">Todas las tiendas</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}" @selected($filters['store_id'] === $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
        @endif
        <select name="sort" data-no-search>
            @foreach ($sorts as $key => $label)
                <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <label style="display:flex;align-items:center;gap:.35rem;font-size:.9rem;white-space:nowrap">
            <input type="checkbox" name="include_unsold" value="1" @checked($filters['include_unsold'])>
            Incluir sin ventas
        </label>
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="meta" style="margin-bottom:1rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
    <div class="card">Unidades vendidas<strong>{{ number_format($totals['units_sold']) }}</strong>
        @if ($totals['units_in_combos'] > 0)<span class="muted" style="font-size:.82rem">{{ number_format($totals['units_in_combos']) }} en combos</span>@endif
    </div>
    <div class="card">Productos distintos<strong>{{ number_format($totals['products']) }}</strong></div>
    <div class="card">Ingreso c/IVA<strong>{{ money($totals['revenue']) }}</strong></div>
    @if ($canSeeCosts)
        <div class="card">Margen bruto<strong>{{ money($totals['revenue'] - $totals['cogs']) }}</strong>
            @if ($totals['revenue'] > 0)<span class="muted" style="font-size:.82rem">{{ number_format(($totals['revenue'] - $totals['cogs']) / $totals['revenue'] * 100, 1) }}%</span>@endif
        </div>
    @endif
    <div class="card">Devoluciones<strong>{{ number_format($totals['units_returned']) }}</strong><span class="muted" style="font-size:.82rem">unidades</span></div>
</div>

<div class="card">
    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="text-align:right">Vendidas</th>
                    <th style="min-width:120px"></th>
                    <th style="text-align:right">En combos</th>
                    <th style="text-align:right">Devol.</th>
                    <th style="text-align:right">Ventas</th>
                    <th style="text-align:right">Ingreso</th>
                    @if ($canSeeCosts)
                        <th style="text-align:right">Margen</th>
                    @endif
                    <th style="text-align:right">Stock</th>
                    <th>Última venta</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $units = (int) $row->units_sold;
                        $stock = (int) $row->stock_on_hand;
                        $margin = (float) $row->revenue - (float) $row->cogs;
                    @endphp
                    <tr @unless ($row->is_active) style="opacity:.6" @endunless>
                        <td>
                            <a href="{{ route('inventory.products.show', $row->id) }}">{{ $row->name }}</a>
                            <div class="muted" style="font-size:.8rem">{{ $row->code }}@unless ($row->is_active) · inactivo @endunless</div>
                        </td>
                        <td style="text-align:right;font-weight:700;font-size:1.05rem">{{ number_format($units) }}</td>
                        <td>
                            <div style="background:#e2e8f0;border-radius:4px;height:8px;overflow:hidden">
                                <div style="width:{{ round($units / $maxUnits * 100) }}%;height:100%;background:var(--signal, #0f766e)"></div>
                            </div>
                        </td>
                        <td style="text-align:right" class="muted">{{ $row->units_in_combos ? number_format($row->units_in_combos) : '—' }}</td>
                        <td style="text-align:right">{{ $row->units_returned ? number_format($row->units_returned) : '—' }}</td>
                        <td style="text-align:right">{{ number_format($row->sales_count) }}</td>
                        <td style="text-align:right">{{ money($row->revenue) }}</td>
                        @if ($canSeeCosts)
                            <td style="text-align:right">{{ money($margin) }}</td>
                        @endif
                        <td style="text-align:right">
                            @if ($row->on_demand)
                                <span class="muted" title="On demand">{{ $stock }}</span>
                            @else
                                <span class="badge {{ $stock <= 0 ? 'badge-off' : '' }}">{{ $stock }} {{ $row->unit }}</span>
                            @endif
                        </td>
                        <td class="muted" style="white-space:nowrap">{{ $row->last_sold_at ? \Illuminate\Support\Carbon::parse($row->last_sold_at)->format('d/m/Y') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $canSeeCosts ? 10 : 9 }}" class="muted">No hay ventas con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:1rem">{{ $rows->links() }}</div>
</div>

<script>
document.getElementById('sold-range-toggle').addEventListener('click', () => {
    document.getElementById('sold-period').value = 'custom';
    const range = document.getElementById('sold-range');
    range.style.display = 'inline-flex';
    range.querySelector('input[name=from]').focus();
});
</script>
@endsection
