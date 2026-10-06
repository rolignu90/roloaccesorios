@php
    $fmt = fn ($n) => '$'.number_format((float) $n, 2);
    $labels = config('sales.payment_methods');
    $diff = (float) $session->difference;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Corte {{ $session->number }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e5e5; font-family: "Courier New", ui-monospace, monospace; color: #000; }
        .ticket { width: 80mm; margin: 1rem auto; background: #fff; padding: 4mm 4mm 6mm; font-size: 12px; line-height: 1.4; }
        .center { text-align: center; }
        .title { font-size: 15px; font-weight: 700; text-transform: uppercase; }
        .sep { border-top: 1px dashed #000; margin: 2.5mm 0; }
        .row { display: flex; justify-content: space-between; gap: 2mm; }
        .row span:last-child { white-space: nowrap; text-align: right; }
        .strong { font-weight: 700; }
        .big { font-size: 15px; font-weight: 700; }
        .toolbar { width: 80mm; margin: 0 auto 1rem; display: flex; gap: .5rem; font-family: system-ui, sans-serif; }
        .toolbar a, .toolbar button { flex: 1; text-align: center; padding: .7rem; border-radius: .4rem; border: 1px solid #111; background: #111; color: #fff; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .toolbar .secondary { background: #fff; color: #111; }
        @media print { body { background: #fff; } .ticket { margin: 0; } .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="ticket">
    <div class="center">
        <div class="title">{{ $store?->name ?? config('app.name') }}</div>
        <div class="strong">{{ $session->isOpen() ? 'CORTE PARCIAL (X)' : 'CORTE DE CAJA (Z)' }}</div>
    </div>
    <div class="sep"></div>
    <div class="row"><span>Caja</span><span>{{ $session->number }}</span></div>
    <div class="row"><span>Apertura</span><span>{{ $session->opened_at->format('d/m/Y H:i') }}</span></div>
    @if ($session->opened_by)<div class="row"><span>Abrió</span><span>{{ $session->opened_by }}</span></div>@endif
    @if ($session->cashier)<div class="row"><span>Cajero</span><span>{{ $session->cashier->name }}</span></div>@endif
    <div class="row"><span>Cierre</span><span>{{ $session->closed_at?->format('d/m/Y H:i') ?? 'Abierta' }}</span></div>
    @if ($session->closed_by)<div class="row"><span>Cerró</span><span>{{ $session->closed_by }}</span></div>@endif
    <div class="row"><span>Impreso</span><span>{{ now()->format('d/m/Y H:i') }}</span></div>

    <div class="sep"></div>
    <div class="strong">VENTAS ({{ $summary['sales_count'] }})</div>
    @foreach (['cash', 'card', 'transfer', 'other'] as $method)
        @continue($method === 'other' && $summary['by_method']['other'] <= 0)
        <div class="row"><span>{{ $labels[$method] ?? 'Otros' }}</span><span>{{ $fmt($summary['by_method'][$method]) }}</span></div>
    @endforeach
    <div class="row strong"><span>Total vendido</span><span>{{ $fmt($summary['sales_total']) }}</span></div>

    <div class="sep"></div>
    <div class="strong">EFECTIVO EN GAVETA</div>
    <div class="row"><span>Fondo inicial</span><span>{{ $fmt($session->opening_amount) }}</span></div>
    <div class="row"><span>+ Ventas efectivo</span><span>{{ $fmt($summary['by_method']['cash']) }}</span></div>
    <div class="row"><span>+ Entradas</span><span>{{ $fmt($summary['cash_in_total']) }}</span></div>
    <div class="row"><span>- Salidas</span><span>{{ $fmt($summary['cash_out_total']) }}</span></div>
    <div class="row big"><span>Esperado</span><span>{{ $fmt($summary['expected_cash']) }}</span></div>
    @unless ($session->isOpen())
        <div class="row"><span>Contado</span><span>{{ $fmt($session->counted_cash) }}</span></div>
        <div class="row big">
            <span>{{ abs($diff) < 0.01 ? 'Cuadrada' : ($diff > 0 ? 'Sobrante' : 'Faltante') }}</span>
            <span>{{ abs($diff) < 0.01 ? $fmt(0) : $fmt(abs($diff)) }}</span>
        </div>
        @if ($session->closing_notes)
            <div class="sep"></div>
            <div>Nota: {{ $session->closing_notes }}</div>
        @endif
    @endunless

    <div class="sep"></div>
    <div style="margin-top:10mm" class="center">______________________<br>Firma</div>
</div>

<div class="toolbar">
    <button type="button" onclick="window.print()">Imprimir</button>
    <a class="secondary" href="{{ route('store.cash.show', $session) }}">Volver a caja</a>
</div>
</body>
</html>
