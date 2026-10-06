<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiquetas de código de barras</title>
    <style>
        @page { size: {{ $size['width'] }}mm {{ $size['height'] }}mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #111; background: #f1f5f9; }
        .setup { max-width: 960px; margin: 1.5rem auto; background: #fff; border-radius: 12px; padding: 1.25rem 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .setup h1 { margin: 0 0 .25rem; font-size: 1.3rem; }
        .muted { color: #64748b; font-size: .9rem; }
        .toolbar { display: flex; flex-wrap: wrap; gap: .6rem; align-items: end; margin: 1rem 0; }
        .toolbar label { display: block; font-size: .8rem; color: #475569; margin-bottom: .2rem; }
        select, input[type=number] { padding: .45rem .55rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: .95rem; }
        input[type=number] { width: 5.5rem; }
        .btn { display: inline-block; padding: .55rem 1rem; border-radius: 8px; border: 0; background: #0f766e; color: #fff; font-weight: 600; cursor: pointer; text-decoration: none; font-size: .95rem; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        th, td { text-align: left; padding: .45rem .5rem; border-bottom: 1px solid #e2e8f0; }
        .warn { background: #fef3c7; color: #92400e; padding: .6rem .8rem; border-radius: 8px; margin-top: .8rem; font-size: .9rem; }
        .preview-title { max-width: 960px; margin: 0 auto .5rem; font-weight: 600; color: #334155; }
        .sheet { max-width: 960px; margin: 0 auto 2rem; display: flex; flex-wrap: wrap; gap: 8px; }

        .label {
            width: {{ $size['width'] }}mm; height: {{ $size['height'] }}mm;
            padding: 1.5mm 2mm; background: #fff; overflow: hidden;
            display: flex; flex-direction: column; align-items: stretch; justify-content: space-between;
            outline: 1px dashed #cbd5e1;
        }
        .label .name {
            font-size: {{ $size['height'] >= 30 ? '9pt' : '7.5pt' }}; font-weight: 600; line-height: 1.15; text-align: center;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .label .bars { flex: 1; min-height: 0; margin: 1mm 0 .5mm; }
        .label .bars svg { width: 100%; height: 100%; display: block; }
        .label .code { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 8pt; letter-spacing: .5px; text-align: center; line-height: 1; }

        @media print {
            body { background: #fff; }
            .setup, .preview-title { display: none; }
            .sheet { display: block; max-width: none; margin: 0; }
            .label { outline: 0; page-break-after: always; break-after: page; }
            .label:last-child { page-break-after: auto; break-after: auto; }
        }
    </style>
</head>
<body>
<div class="setup">
    <h1>Etiquetas de código de barras</h1>
    <p class="muted" style="margin:0">Code 128 con el código del producto. El lector del punto de venta lo reconoce al escanear.</p>

    <form method="GET" action="{{ route('inventory.products.labels') }}">
        <div class="toolbar">
            <div>
                <label for="size">Tamaño de etiqueta</label>
                <select id="size" name="size">
                    @foreach ($sizes as $key => $option)
                        <option value="{{ $key }}" @selected($key === $sizeKey)>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-secondary" type="submit">Actualizar vista</button>
            <button class="btn" type="button" onclick="window.print()" @disabled($total === 0)>Imprimir {{ $total }} {{ $total === 1 ? 'etiqueta' : 'etiquetas' }}</button>
            <a class="btn btn-secondary" href="{{ route('inventory.products.index') }}">Volver a productos</a>
        </div>

        @if ($products->isEmpty())
            <p class="muted">No hay productos seleccionados. En el listado de productos marca los que quieras y pulsa «Imprimir etiquetas».</p>
        @else
            <table>
                <thead><tr><th>Código</th><th>Producto</th><th style="width:7rem">Copias</th></tr></thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td><code>{{ $product->code }}</code></td>
                            <td>{{ $product->name }}</td>
                            <td><input type="number" name="items[{{ $product->id }}]" min="0" max="500" value="{{ $quantities->get($product->id, 1) }}"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="muted" style="margin:.6rem 0 0">Cambia las copias y pulsa «Actualizar vista». Pon 0 para quitar un producto.</p>
        @endif

        @if ($truncated)
            <div class="warn">Se muestran solo las primeras {{ $maxLabels }} etiquetas. Imprime en varias tandas.</div>
        @endif
        <div class="warn">En el diálogo de impresión elige la impresora de etiquetas, tamaño de papel {{ $size['label'] }}, márgenes «Ninguno» y escala 100%.</div>
    </form>
</div>

@if ($total > 0)
    <div class="preview-title">Vista previa</div>
    <div class="sheet">
        @php $printed = 0; @endphp
        @foreach ($products as $product)
            @php
                $copies = (int) $quantities->get($product->id, 0);
                $svg = \App\Support\Code128::supports($product->code) ? \App\Support\Code128::svg($product->code) : null;
            @endphp
            @for ($i = 0; $i < $copies && $printed < $maxLabels; $i++, $printed++)
                <div class="label">
                    <div class="name">{{ $product->name }}</div>
                    <div class="bars">
                        @if ($svg)
                            {!! $svg !!}
                        @else
                            <div class="muted" style="font-size:7pt;text-align:center">Código con caracteres no válidos</div>
                        @endif
                    </div>
                    <div class="code">{{ $product->code }}</div>
                </div>
            @endfor
        @endforeach
    </div>
@endif
</body>
</html>
