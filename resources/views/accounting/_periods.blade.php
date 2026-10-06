@php
    $today = now();
    $presets = [
        'Mes actual' => [$today->copy()->startOfMonth(), $today->copy()],
        'Mes pasado' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
        'Últimos 3 meses' => [$today->copy()->subMonthsNoOverflow(2)->startOfMonth(), $today->copy()],
    ];
    $query = request()->except(['from', 'to', 'page']);
    $from = $from ?? null;
    $to = $to ?? null;
@endphp
<div style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin-bottom:.75rem">
    <strong style="font-size:.9rem;margin-right:.25rem">Período:</strong>
    @foreach ($presets as $label => [$presetFrom, $presetTo])
        @php $active = $from && $to && $from->toDateString() === $presetFrom->toDateString() && $to->toDateString() === $presetTo->toDateString(); @endphp
        <a class="btn {{ $active ? '' : 'btn-secondary' }}" style="padding:.35rem .75rem;font-size:.88rem"
           title="{{ $presetFrom->format('d/m/Y') }} – {{ $presetTo->format('d/m/Y') }}"
           href="{{ url()->current().'?'.http_build_query(array_merge($query, ['from' => $presetFrom->toDateString(), 'to' => $presetTo->toDateString()])) }}">{{ $label }}</a>
    @endforeach
    @if ($from || $to)
        <span class="muted" style="font-size:.85rem">{{ $from?->format('d/m/Y') ?? '…' }} – {{ $to?->format('d/m/Y') ?? 'hoy' }}</span>
    @endif
</div>
