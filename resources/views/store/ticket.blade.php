@php
    $fmt = fn ($n) => '$'.number_format((float) $n, 2);
    $loose = $sale->items->whereNull('combo_id');
    $comboGroups = $sale->items->whereNotNull('combo_id')->groupBy('combo_id');
    $gross = $sale->items->sum(fn ($item) => price_with_vat($item->unit_price_without_vat) * $item->quantity);
    $discount = max(0, round($gross - (float) $sale->total, 2));
    $autoPrint = request()->boolean('print');
    $methodLabel = config('sales.payment_methods')[$sale->payment_method] ?? $sale->payment_method;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket {{ $sale->number }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e5e5; font-family: "Courier New", ui-monospace, monospace; color: #000; }
        .ticket { width: 72mm; margin: 1rem auto; background: #fff; padding: 2mm 2mm 4mm; font-size: 12px; line-height: 1.35; }
        .center { text-align: center; }
        .store-name { font-size: 16px; font-weight: 700; text-transform: uppercase; }
        .sep { border-top: 1px dashed #000; margin: 2.5mm 0; }
        .row { display: flex; justify-content: space-between; gap: 2mm; }
        .row span:last-child { text-align: right; white-space: nowrap; }
        .item-name { font-weight: 700; }
        .sub { padding-left: 3mm; font-size: 11px; }
        .total { font-size: 16px; font-weight: 700; }
        .toolbar { width: 72mm; margin: 0 auto 1rem; display: flex; gap: .5rem; font-family: system-ui, sans-serif; }
        .toolbar a, .toolbar button { flex: 1; text-align: center; padding: .7rem; border-radius: .4rem; border: 1px solid #111; background: #111; color: #fff; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .toolbar .secondary { background: #fff; color: #111; }
        @media print {
            body { background: #fff; }
            .ticket { margin: 0; width: 72mm; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
<div class="ticket">
    <div class="center">
        <div class="store-name">{{ $store?->name ?? config('app.name') }}</div>
        @if ($store?->address)<div>{{ $store->address }}</div>@endif
        @if ($store?->phone)<div>Tel. {{ $store->phone }}</div>@endif
        @if ($store?->tax_id)<div>NIT/NRC {{ $store->tax_id }}</div>@endif
    </div>

    <div class="sep"></div>
    <div class="row"><span>Ticket</span><span>{{ $sale->number }}</span></div>
    <div class="row"><span>Fecha</span><span>{{ $sale->sold_at?->format('d/m/Y H:i') }}</span></div>
    @if ($sale->seller)<div class="row"><span>Atendió</span><span>{{ $sale->seller->name }}</span></div>@endif
    @if ($sale->customer && $sale->customer->name !== config('sales.store.walk_in_customer_name', 'Consumidor final'))
        <div class="row"><span>Cliente</span><span>{{ $sale->customer->name }}</span></div>
    @endif
    @if ($sale->status === \App\Models\Sale::STATUS_VOIDED)
        <div class="center total" style="margin-top:2mm">*** ANULADA ***</div>
    @endif

    <div class="sep"></div>
    @foreach ($loose as $item)
        @php $unit = price_with_vat($item->unit_price_without_vat); @endphp
        <div class="item-name">{{ $item->product?->name ?? 'Producto' }}</div>
        <div class="row sub"><span>{{ $item->quantity }} x {{ $fmt($unit) }}</span><span>{{ $fmt($unit * $item->quantity) }}</span></div>
    @endforeach
    @foreach ($comboGroups as $lines)
        @php $comboGross = $lines->sum(fn ($item) => price_with_vat($item->unit_price_without_vat) * $item->quantity); @endphp
        <div class="row item-name"><span>Combo {{ $lines->first()->combo?->name }}</span><span>{{ $fmt($comboGross) }}</span></div>
        @foreach ($lines as $item)
            <div class="sub">{{ $item->quantity }} x {{ $item->product?->name }}</div>
        @endforeach
    @endforeach

    <div class="sep"></div>
    @if ($discount > 0.009)
        <div class="row"><span>Subtotal</span><span>{{ $fmt($gross) }}</span></div>
        <div class="row"><span>Descuento</span><span>-{{ $fmt($discount) }}</span></div>
    @endif
    <div class="row total"><span>TOTAL</span><span>{{ $fmt($sale->total) }}</span></div>
    <div class="row sub"><span>IVA incluido</span><span>{{ $fmt($sale->vat_amount) }}</span></div>

    <div class="sep"></div>
    <div class="row"><span>Pago</span><span>{{ $methodLabel }}</span></div>
    @if ($sale->amount_received !== null)
        <div class="row"><span>Recibido</span><span>{{ $fmt($sale->amount_received) }}</span></div>
        <div class="row"><span>Cambio</span><span>{{ $fmt($sale->change_given) }}</span></div>
    @endif

    <div class="sep"></div>
    <div class="center">{{ $store?->ticketFooter() ?? '¡Gracias por su compra!' }}</div>
</div>

<div class="toolbar">
    <a href="{{ route('store.pos') }}">Nueva venta</a>
    <button type="button" class="secondary" onclick="window.print()">Reimprimir</button>
    <a class="secondary" href="{{ route('sales.sales.show', $sale) }}">Ver venta</a>
</div>

<script>
    try { sessionStorage.removeItem(@json('rolo-pos-cart-'.($sale->store_id ?? ''))); } catch (e) {}
    @if ($autoPrint)
        window.addEventListener('load', () => {
            window.addEventListener('afterprint', () => { window.location.href = @json(route('store.pos')); }, { once: true });
            setTimeout(() => window.print(), 250);
        });
    @endif
</script>
</body>
</html>
