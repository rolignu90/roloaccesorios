@php
    $product = $product ?? null;
    $existingSuppliers = old('suppliers', $product?->productSuppliers?->map(fn ($row) => [
        'supplier_id' => $row->supplier_id,
        'purchase_price' => $row->purchase_price,
        'is_preferred' => $row->is_preferred,
    ])->values()->all() ?? [['supplier_id' => '', 'purchase_price' => '', 'is_preferred' => true]]);
    if (empty($existingSuppliers)) {
        $existingSuppliers = [['supplier_id' => '', 'purchase_price' => '', 'is_preferred' => true]];
    }
@endphp

<div class="grid-2">
    <div class="field">
        <label for="code">Código *</label>
        <input id="code" type="text" name="code" value="{{ old('code', $product?->code) }}" required>
    </div>
    <div class="field">
        <label for="name">Nombre *</label>
        <input id="name" type="text" name="name" value="{{ old('name', $product?->name) }}" required>
    </div>
</div>
<div class="field">
    <label for="description">Descripción</label>
    <textarea id="description" name="description">{{ old('description', $product?->description) }}</textarea>
</div>
<div class="grid-3">
    <div class="field">
        <label for="unit">Unidad *</label>
        <input id="unit" type="text" name="unit" value="{{ old('unit', $product?->unit ?? 'unidad') }}" required>
    </div>
    <div class="field">
        <label for="weight">Peso (kg)</label>
        <input id="weight" type="number" min="0" step="0.001" name="weight" value="{{ old('weight', $product?->weight) }}" placeholder="Para etiquetas de envío">
    </div>
    <div class="field" style="display:flex;align-items:flex-end;padding-bottom:.4rem;gap:1.25rem;flex-wrap:wrap">
        <input type="hidden" name="is_active" value="0">
        <label>
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product?->is_active ?? true))>
            Activo
        </label>
        <input type="hidden" name="free_shipping" value="0">
        <label>
            <input type="checkbox" name="free_shipping" value="1" @checked(old('free_shipping', $product?->free_shipping ?? false))>
            Envío gratis
        </label>
        <input type="hidden" name="on_demand" value="0">
        <label>
            <input type="checkbox" name="on_demand" value="1" @checked(old('on_demand', $product?->on_demand ?? false))>
            On demand (vender sin stock)
        </label>
    </div>
    <p class="muted" style="margin:.35rem 0 0;width:100%">Si hay stock, primero se agota FIFO; solo lo que falte queda como on demand (para comprar).</p>
</div>
<div class="grid-3">
    <div class="field">
        <label for="sale_price_with_vat">Precio de venta con IVA (USD) *</label>
        <input
            id="sale_price_with_vat"
            type="number"
            min="0"
            step="0.01"
            name="sale_price_with_vat"
            value="{{ old('sale_price_with_vat', $product ? number_format($product->salePriceWithVat(), 2, '.', '') : '0.00') }}"
            required
        >
        <p class="muted" style="margin:.35rem 0 0">
            IVA {{ number_format(vat_rate() * 100, 0) }}% · se guarda la base sin IVA automáticamente
            @if ($product?->exists)
                (base actual {{ money($product->sale_price_without_vat) }})
            @endif
        </p>
    </div>
    <div class="field">
        <label for="wholesale_price_with_vat">Precio reventa / mayoreo c/IVA (USD)</label>
        <input
            id="wholesale_price_with_vat"
            type="number"
            min="0"
            step="0.01"
            name="wholesale_price_with_vat"
            value="{{ old('wholesale_price_with_vat', $product && $product->wholesale_price_without_vat !== null ? number_format($product->wholesalePriceWithVat(), 2, '.', '') : '') }}"
            placeholder="Para consignación y mayoreo"
        >
        <p class="muted" style="margin:.35rem 0 0">Usado al entregar en consignación a vendedores o clientes. Opcional.</p>
    </div>
    <div class="field">
        <label for="min_stock">Stock mínimo (alertas) *</label>
        <input id="min_stock" type="number" min="0" name="min_stock" value="{{ old('min_stock', $product?->min_stock ?? 0) }}" required>
        <p class="muted" style="margin:.35rem 0 0">Si el stock llega a este nivel o menos, se marca en alerta. Usa 0 para no alertar. No aplica a productos on demand.</p>
    </div>
</div>
<div class="field">
    <label for="stock_on_hand">Stock actual</label>
    @php
        $currentStock = (int) ($stockOnHand ?? $product?->stock_on_hand ?? $product?->stockOnHand() ?? 0);
    @endphp
    <input id="stock_on_hand" type="number" value="{{ $currentStock }}" disabled>
    <p class="muted" style="margin:.35rem 0 0">
        Se actualiza con entradas de stock, ventas y consignaciones (FIFO).
        @if ($product?->exists)
            <a href="{{ route('inventory.stock.create', ['product_id' => $product->id]) }}">Registrar entrada</a>
        @endif
    </p>
</div>

<div class="card" style="margin:1rem 0;padding:1rem;background:#f9fafb" data-promo-box>
    <div class="topbar" style="margin-bottom:.75rem">
        <div>
            <strong>Precio de promoción</strong>
            <p class="muted" style="margin:.25rem 0 0">Opcional. Puede ser un precio fijo (monto c/IVA) o un descuento en porcentaje.</p>
        </div>
        <label style="display:flex;align-items:center;gap:.4rem;font-weight:500">
            <input type="hidden" name="promo_active" value="0">
            <input type="checkbox" name="promo_active" value="1" id="promo_active" @checked(old('promo_active', $product?->promo_active)) data-promo-toggle>
            Activar promo
        </label>
    </div>
    <div class="grid-2" data-promo-fields>
        <div class="field">
            <label for="promo_type">Tipo</label>
            <select id="promo_type" name="promo_type" data-promo-type>
                <option value="amount" @selected(old('promo_type', $product?->promo_type ?? 'amount') === 'amount')>Monto (precio promo c/IVA)</option>
                <option value="percent" @selected(old('promo_type', $product?->promo_type) === 'percent')>Porcentaje de descuento</option>
            </select>
        </div>
        <div class="field">
            <label for="promo_value" data-promo-value-label>
                @if (old('promo_type', $product?->promo_type ?? 'amount') === 'percent')
                    Descuento (%)
                @else
                    Precio promo c/IVA (USD)
                @endif
            </label>
            <input
                id="promo_value"
                type="number"
                min="0"
                step="0.01"
                name="promo_value"
                value="{{ old('promo_value', $product?->promo_value) }}"
                data-promo-value
            >
            <p class="muted" style="margin:.35rem 0 0" data-promo-hint>
                @if (old('promo_type', $product?->promo_type ?? 'amount') === 'percent')
                    Se aplica sobre el precio de venta con IVA.
                @else
                    Este será el precio de venta con IVA mientras la promo esté activa.
                @endif
            </p>
        </div>
    </div>
</div>

<div class="card" style="margin:1rem 0;padding:1rem;background:#f9fafb">
    <div class="topbar" style="margin-bottom:.75rem">
        <div>
            <strong>Proveedores y precios de compra</strong>
            <p class="muted" style="margin:.25rem 0 0">Un producto puede tener varios proveedores, cada uno con su precio de compra. El precio de venta es único.</p>
        </div>
        <button type="button" class="btn btn-secondary" id="add-supplier-row">Agregar proveedor</button>
    </div>

    <div id="supplier-rows">
        @foreach ($existingSuppliers as $index => $row)
            <div class="supplier-row grid-3" data-row>
                <div class="field">
                    <label>Proveedor</label>
                    <select name="suppliers[{{ $index }}][supplier_id]">
                        <option value="">— Selecciona —</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(($row['supplier_id'] ?? null) == $supplier->id)>
                                {{ $supplier->code }} — {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Precio compra (USD)</label>
                    <input type="number" min="0" step="0.01" name="suppliers[{{ $index }}][purchase_price]" value="{{ $row['purchase_price'] ?? '' }}">
                </div>
                <div class="field" style="display:flex;align-items:flex-end;gap:.75rem;padding-bottom:.4rem">
                    <label>
                        <input type="radio" name="preferred_supplier_index" value="{{ $index }}" @checked(!empty($row['is_preferred']) || $index === 0)>
                        Preferido
                    </label>
                    <button type="button" class="btn-link remove-supplier-row" style="color:#b91c1c;border:0;background:transparent;cursor:pointer">Quitar</button>
                </div>
                <input type="hidden" name="suppliers[{{ $index }}][is_preferred]" value="{{ !empty($row['is_preferred']) || $index === 0 ? 1 : 0 }}" data-preferred-flag>
            </div>
        @endforeach
    </div>
</div>

<p class="muted">Todos los precios están en dólares estadounidenses (USD).</p>

<template id="supplier-row-template">
    <div class="supplier-row grid-3" data-row>
        <div class="field">
            <label>Proveedor</label>
            <select name="suppliers[__INDEX__][supplier_id]">
                <option value="">— Selecciona —</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Precio compra (USD)</label>
            <input type="number" min="0" step="0.01" name="suppliers[__INDEX__][purchase_price]" value="">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;gap:.75rem;padding-bottom:.4rem">
            <label>
                <input type="radio" name="preferred_supplier_index" value="__INDEX__">
                Preferido
            </label>
            <button type="button" class="btn-link remove-supplier-row" style="color:#b91c1c;border:0;background:transparent;cursor:pointer">Quitar</button>
        </div>
        <input type="hidden" name="suppliers[__INDEX__][is_preferred]" value="0" data-preferred-flag>
    </div>
</template>

<script>
(() => {
    const rows = document.getElementById('supplier-rows');
    const template = document.getElementById('supplier-row-template');
    const addBtn = document.getElementById('add-supplier-row');

    const syncPreferredFlags = () => {
        const radios = rows.querySelectorAll('input[type="radio"][name="preferred_supplier_index"]');
        radios.forEach((radio) => {
            const flag = radio.closest('[data-row]')?.querySelector('[data-preferred-flag]');
            if (flag) {
                flag.value = radio.checked ? '1' : '0';
            }
        });
    };

    const reindex = () => {
        [...rows.querySelectorAll('[data-row]')].forEach((row, index) => {
            row.querySelectorAll('select, input').forEach((el) => {
                if (el.name) {
                    el.name = el.name.replace(/suppliers\[\d+]/, `suppliers[${index}]`);
                }
                if (el.name === 'preferred_supplier_index' || el.getAttribute('name') === 'preferred_supplier_index') {
                    el.value = String(index);
                }
                if (el.type === 'radio') {
                    el.value = String(index);
                }
            });
        });
        syncPreferredFlags();
    };

    addBtn?.addEventListener('click', () => {
        const index = rows.querySelectorAll('[data-row]').length;
        const html = template.innerHTML.replaceAll('__INDEX__', String(index));
        rows.insertAdjacentHTML('beforeend', html);
        const newRow = rows.querySelector('[data-row]:last-child');
        window.initSearchableSelects?.(newRow);
        reindex();
    });

    rows?.addEventListener('click', (event) => {
        if (event.target.closest('.remove-supplier-row')) {
            const row = event.target.closest('[data-row]');
            if (rows.querySelectorAll('[data-row]').length > 1) {
                row.querySelectorAll('select').forEach((el) => window.destroySearchableSelect?.(el));
                row.remove();
                reindex();
            }
        }
    });

    rows?.addEventListener('change', (event) => {
        if (event.target.matches('input[type="radio"][name="preferred_supplier_index"]')) {
            syncPreferredFlags();
        }
    });

    document.querySelector('form')?.addEventListener('submit', syncPreferredFlags);
    syncPreferredFlags();
})();

(() => {
    const toggle = document.querySelector('[data-promo-toggle]');
    const fields = document.querySelector('[data-promo-fields]');
    const typeSelect = document.querySelector('[data-promo-type]');
    const valueLabel = document.querySelector('[data-promo-value-label]');
    const hint = document.querySelector('[data-promo-hint]');
    const valueInput = document.querySelector('[data-promo-value]');

    const syncTypeLabels = () => {
        const isPercent = typeSelect?.value === 'percent';
        if (valueLabel) {
            valueLabel.textContent = isPercent ? 'Descuento (%)' : 'Precio promo c/IVA (USD)';
        }
        if (hint) {
            hint.textContent = isPercent
                ? 'Se aplica sobre el precio de venta con IVA.'
                : 'Este será el precio de venta con IVA mientras la promo esté activa.';
        }
        if (valueInput) {
            valueInput.max = isPercent ? '100' : '';
            valueInput.step = isPercent ? '0.01' : '0.01';
        }
    };

    const syncEnabled = () => {
        const on = !!toggle?.checked;
        fields?.querySelectorAll('select, input').forEach((el) => {
            el.disabled = !on;
        });
        if (fields) fields.style.opacity = on ? '1' : '.55';
    };

    toggle?.addEventListener('change', syncEnabled);
    typeSelect?.addEventListener('change', syncTypeLabels);
    syncTypeLabels();
    syncEnabled();
})();
</script>
