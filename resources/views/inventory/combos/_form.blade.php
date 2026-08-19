@php
    $combo = $combo ?? null;
    $existingItems = old('items', $combo?->items?->map(fn ($row) => [
        'product_id' => $row->product_id,
        'quantity' => $row->quantity,
    ])->values()->all() ?? [
        ['product_id' => '', 'quantity' => 1],
        ['product_id' => '', 'quantity' => 1],
    ]);
    if (count($existingItems) < 2) {
        $existingItems[] = ['product_id' => '', 'quantity' => 1];
    }
@endphp

<div class="grid-2">
    <div class="field">
        <label for="code">Código *</label>
        <input id="code" type="text" name="code" value="{{ old('code', $combo?->code) }}" required>
    </div>
    <div class="field">
        <label for="name">Nombre *</label>
        <input id="name" type="text" name="name" value="{{ old('name', $combo?->name) }}" required>
    </div>
</div>

<div class="field">
    <label for="description">Descripción</label>
    <textarea id="description" name="description">{{ old('description', $combo?->description) }}</textarea>
</div>

<div class="grid-2">
    <div class="field">
        <label for="sale_price_with_vat">Precio del combo c/IVA (USD) *</label>
        <input
            id="sale_price_with_vat"
            type="number"
            min="0"
            step="0.01"
            name="sale_price_with_vat"
            value="{{ old('sale_price_with_vat', $combo ? number_format($combo->salePriceWithVat(), 2, '.', '') : '') }}"
            required
        >
        <p class="muted" style="margin:.35rem 0 0">Ej. 14.99 — al vender se reparte entre los productos del combo.</p>
    </div>
    <div class="field" style="display:flex;align-items:flex-end;padding-bottom:.4rem;gap:1rem;flex-wrap:wrap">
        <input type="hidden" name="is_active" value="0">
        <label>
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $combo?->is_active ?? true))>
            Activo
        </label>
        <input type="hidden" name="free_shipping" value="0">
        <label>
            <input type="checkbox" name="free_shipping" value="1" @checked(old('free_shipping', $combo?->free_shipping ?? false))>
            Envío gratis
        </label>
    </div>
</div>

<div class="card" style="margin:1rem 0;padding:1rem;background:#f9fafb">
    <div class="topbar" style="margin-bottom:.75rem">
        <strong>Productos del combo *</strong>
        <button type="button" class="btn btn-secondary" id="add-combo-item">Agregar producto</button>
    </div>
    <div id="combo-item-rows">
        @foreach ($existingItems as $index => $item)
            <div class="grid-3" data-combo-row style="align-items:end;margin-bottom:.5rem">
                <div class="field" style="grid-column: span 2">
                    <label>Producto</label>
                    <select name="items[{{ $index }}][product_id]" required>
                        <option value="">— Selecciona —</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected(($item['product_id'] ?? null) == $product->id)>
                                {{ $product->code }} — {{ $product->name }}
                                (stock {{ (int) ($product->stock_on_hand ?? 0) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Cantidad</label>
                    <div style="display:flex;gap:.5rem;align-items:center">
                        <input type="number" min="1" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" required>
                        <button type="button" class="btn-link remove-combo-item" style="color:#b91c1c;border:0;background:transparent;cursor:pointer">Quitar</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<template id="combo-item-template">
    <div class="grid-3" data-combo-row style="align-items:end;margin-bottom:.5rem">
        <div class="field" style="grid-column: span 2">
            <label>Producto</label>
            <select name="items[__INDEX__][product_id]" required>
                <option value="">— Selecciona —</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}">
                        {{ $product->code }} — {{ $product->name }}
                        (stock {{ (int) ($product->stock_on_hand ?? 0) }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Cantidad</label>
            <div style="display:flex;gap:.5rem;align-items:center">
                <input type="number" min="1" name="items[__INDEX__][quantity]" value="1" required>
                <button type="button" class="btn-link remove-combo-item" style="color:#b91c1c;border:0;background:transparent;cursor:pointer">Quitar</button>
            </div>
        </div>
    </div>
</template>

<script>
(() => {
    const rows = document.getElementById('combo-item-rows');
    const template = document.getElementById('combo-item-template');
    if (!rows || !template) return;

    const reindex = () => {
        [...rows.querySelectorAll('[data-combo-row]')].forEach((row, index) => {
            row.querySelectorAll('select, input').forEach((el) => {
                if (!el.name) return;
                el.name = el.name.replace(/items\[\d+]/, `items[${index}]`);
            });
        });
        window.initSearchableSelects?.(rows);
    };

    document.getElementById('add-combo-item')?.addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', String(rows.querySelectorAll('[data-combo-row]').length));
        rows.insertAdjacentHTML('beforeend', html);
        reindex();
    });

    rows.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-combo-item');
        if (!btn) return;
        const row = btn.closest('[data-combo-row]');
        if (!row) return;
        if (rows.querySelectorAll('[data-combo-row]').length <= 2) {
            alert('Un combo necesita al menos 2 productos.');
            return;
        }
        row.remove();
        reindex();
    });

    reindex();
})();
</script>
