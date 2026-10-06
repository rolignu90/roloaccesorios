<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Abrir caja</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e5e5; font-family: "Courier New", ui-monospace, monospace; color: #000; }
        .slip { width: 72mm; margin: 1rem auto; background: #fff; padding: 3mm 2mm; font-size: 12px; line-height: 1.35; text-align: center; }
        .title { font-size: 14px; font-weight: 700; }
        .toolbar { width: 72mm; margin: 0 auto 1rem; display: flex; gap: .5rem; font-family: system-ui, sans-serif; }
        .toolbar a, .toolbar button { flex: 1; text-align: center; padding: .7rem; border-radius: .4rem; border: 1px solid #111; background: #111; color: #fff; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .toolbar .secondary { background: #fff; color: #111; }
        .help { width: 72mm; margin: 0 auto; font-family: system-ui, sans-serif; font-size: 13px; color: #444; }
        @media print {
            body { background: #fff; }
            .slip { margin: 0; }
            .toolbar, .help { display: none; }
        }
    </style>
</head>
<body>
<div class="slip">
    <div class="title">APERTURA DE CAJA</div>
    <div>{{ $store->name }}</div>
    <div>{{ now()->format('d/m/Y H:i') }} · {{ $user->name }}</div>
    @if ($session)<div>{{ $session->number }}</div>@endif
</div>

<div class="toolbar">
    <a href="{{ route('store.pos') }}">Volver al POS</a>
    <button type="button" class="secondary" onclick="window.print()">Abrir otra vez</button>
</div>
<p class="help">La gaveta se abre cuando la impresora de tickets termina de imprimir. Si no se abrió, revisa que la impresora POS-80 esté seleccionada y que en su driver esté activo «Cash Drawer: After Printing».</p>

<script>
    window.addEventListener('load', () => {
        window.addEventListener('afterprint', () => { window.location.href = @json(route('store.pos')); }, { once: true });
        setTimeout(() => window.print(), 150);
    });
</script>
</body>
</html>
