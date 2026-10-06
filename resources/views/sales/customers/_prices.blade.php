@php
    $priceGroups = $priceGroups ?? collect();
    $products = $products ?? collect();
    $existingTiersByProduct = $priceGroups->mapWithKeys(function ($group) {
        $productId = (string) ($group['product']->id ?? '');
        if ($productId === '') {
            return [];
        }

        return [
            $productId => $group['tiers']->map(fn ($tier) => [
                'min_quantity' => (int) $tier->min_quantity,
                'unit_price_with_vat' => number_format($tier->unitPriceWithVat(), 2, '.', ''),
            ])->values()->all(),
        ];
    })->all();
@endphp

<div class="card" style="margin-top:1rem;border:2px solid var(--signal)">
    <div class="topbar" style="margin-bottom:.75rem">
        <div>
            <h2 style="margin:0;font-size:1.15rem">Precios especiales por producto</h2>
            <p class="muted" style="margin:.35rem 0 0">
                Define tramos por cantidad para este cliente (ej. desde 1, 3, 6, 9…).
                En ventas y consignaciones se aplica automáticamente el tramo según la cantidad.
            </p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Tramos</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($priceGroups as $group)
                <tr>
                    <td>{{ $group['product']?->code }} — {{ $group['product']?->name }}</td>
                    <td>
                        @foreach ($group['tiers'] as $tier)
                            <span class="badge" style="margin:.15rem">
                                ≥ {{ $tier->min_quantity }} → {{ money($tier->unitPriceWithVat()) }}
                            </span>
                        @endforeach
                    </td>
                    <td style="white-space:nowrap">
                        <button
                            type="button"
                            class="btn-link"
                            style="border:0;background:transparent;cursor:pointer;margin-right:.75rem"
                            data-edit-product-prices
                            data-product-id="{{ $group['product']->id }}"
                        >Editar</button>
                        <form method="POST" action="{{ route('sales.customers.prices.destroy', [$customer, $group['product']]) }}" onsubmit="return confirm('¿Eliminar precios de este producto para el cliente?')" style="display:inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-link" style="color:var(--danger);border:0;background:transparent;cursor:pointer">Quitar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">Aún no hay precios especiales. Usa el formulario de abajo. Si no defines nada, se usa mayoreo del producto o precio de venta.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card" style="margin-top:1rem" id="customer-price-form">
    <h2 style="margin-top:0;font-size:1.1rem" id="price-form-title">Agregar / actualizar precios de un producto</h2>
    <p class="muted" id="price-form-help">Ejemplo: desde 1 → $18, desde 3 → $16, desde 6 → $14. Si el producto ya tiene tramos, se reemplazan.</p>
    <form method="POST" action="{{ route('sales.customers.prices.sync', $customer) }}" id="price-tiers-form">
        @csrf
        <div class="field">
            <label for="product_id">Producto *</label>
            <select id="product_id" name="product_id" required>
                <option value="">— Selecciona —</option>
                @foreach ($products as $product)
                    <option
                        value="{{ $product->id }}"
                        data-wholesale="{{ $product->wholesalePriceWithVat() !== null ? number_format($product->wholesalePriceWithVat(), 2, '.', '') : '' }}"
                        data-sale="{{ number_format($product->salePriceWithVat(), 2, '.', '') }}"
                        @selected(old('product_id') == $product->id)
                    >
                        {{ $product->code }} — {{ $product->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div id="tier-rows">
            @php
                $oldTiers = old('tiers', [
                    ['min_quantity' => 1, 'unit_price_with_vat' => ''],
                ]);
            @endphp
            @foreach ($oldTiers as $i => $tier)
                <div class="grid-3" data-tier-row style="align-items:end">
                    <div class="field">
                        <label>Desde cantidad *</label>
                        <input type="number" min="1" name="tiers[{{ $i }}][min_quantity]" value="{{ $tier['min_quantity'] ?? 1 }}" required>
                    </div>
                    <div class="field">
                        <label>Precio unitario c/IVA *</label>
                        <input type="number" min="0" step="0.01" name="tiers[{{ $i }}][unit_price_with_vat]" value="{{ $tier['unit_price_with_vat'] ?? '' }}" required>
                    </div>
                    <div class="field" style="display:flex;align-items:flex-end;padding-bottom:.4rem">
                        <button type="button" class="btn-link remove-tier" style="color:var(--danger);border:0;background:transparent;cursor:pointer">Quitar tramo</button>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="actions" style="margin-bottom:1rem">
            <button type="button" class="btn btn-secondary" id="add-tier">Agregar tramo</button>
            <button type="submit" class="btn" id="price-form-submit">Guardar precios</button>
        </div>
    </form>
</div>

<template id="tier-template">
    <div class="grid-3" data-tier-row style="align-items:end">
        <div class="field">
            <label>Desde cantidad *</label>
            <input type="number" min="1" name="tiers[__INDEX__][min_quantity]" value="1" required>
        </div>
        <div class="field">
            <label>Precio unitario c/IVA *</label>
            <input type="number" min="0" step="0.01" name="tiers[__INDEX__][unit_price_with_vat]" value="" required>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;padding-bottom:.4rem">
            <button type="button" class="btn-link remove-tier" style="color:var(--danger);border:0;background:transparent;cursor:pointer">Quitar tramo</button>
        </div>
    </div>
</template>

<script>
(() => {
    const rows = document.getElementById('tier-rows');
    const template = document.getElementById('tier-template');
    const productSelect = document.getElementById('product_id');
    const formCard = document.getElementById('customer-price-form');
    const titleEl = document.getElementById('price-form-title');
    const helpEl = document.getElementById('price-form-help');
    const existingTiersByProduct = @json($existingTiersByProduct);
    if (!rows || !template) return;

    const reindex = () => {
        [...rows.querySelectorAll('[data-tier-row]')].forEach((row, index) => {
            row.querySelectorAll('input').forEach((el) => {
                if (el.name) el.name = el.name.replace(/tiers\[\d+]/, `tiers[${index}]`);
            });
        });
    };

    const clearTierRows = () => {
        rows.innerHTML = '';
    };

    const addTierRow = (minQuantity = 1, price = '') => {
        const index = rows.querySelectorAll('[data-tier-row]').length;
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
        const row = rows.querySelector('[data-tier-row]:last-child');
        const minInput = row?.querySelector('input[name*="[min_quantity]"]');
        const priceInput = row?.querySelector('input[name*="[unit_price_with_vat]"]');
        if (minInput) minInput.value = String(minQuantity || 1);
        if (priceInput) priceInput.value = price !== null && price !== undefined ? String(price) : '';
        reindex();
    };

    const setFormMode = (editing, productLabel = '') => {
        if (titleEl) {
            titleEl.textContent = editing
                ? ('Editar precios' + (productLabel ? ': ' + productLabel : ''))
                : 'Agregar / actualizar precios de un producto';
        }
        if (helpEl) {
            helpEl.textContent = editing
                ? 'Modifica los tramos y guarda. Se reemplazan los precios actuales de este producto.'
                : 'Ejemplo: desde 1 → $18, desde 3 → $16, desde 6 → $14. Si el producto ya tiene tramos, se reemplazan.';
        }
    };

    const loadTiersForProduct = (productId, { preferExisting = true, scroll = false } = {}) => {
        const id = String(productId || '');
        const existing = preferExisting ? (existingTiersByProduct[id] || []) : [];
        clearTierRows();

        if (existing.length) {
            existing.forEach((tier) => addTierRow(tier.min_quantity, tier.unit_price_with_vat));
            const opt = productSelect?.selectedOptions?.[0];
            setFormMode(true, opt?.textContent?.trim() || '');
        } else {
            const opt = productSelect?.selectedOptions?.[0];
            const fallback = opt?.dataset?.wholesale || opt?.dataset?.sale || '';
            addTierRow(1, fallback);
            setFormMode(false);
        }

        if (scroll && formCard) {
            formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            productSelect?.focus?.();
        }
    };

    document.getElementById('add-tier')?.addEventListener('click', () => {
        addTierRow(1, '');
        window.initSearchableSelects?.(document.getElementById('customer-price-form'));
    });

    rows.addEventListener('click', (e) => {
        if (e.target.closest('.remove-tier')) {
            const row = e.target.closest('[data-tier-row]');
            if (rows.querySelectorAll('[data-tier-row]').length > 1) {
                row.remove();
                reindex();
            }
        }
    });

    const onProductChange = () => {
        loadTiersForProduct(productSelect?.value || '', { preferExisting: true, scroll: false });
    };
    productSelect?.addEventListener('change', onProductChange);
    productSelect?.addEventListener('searchable:change', onProductChange);

    document.querySelectorAll('[data-edit-product-prices]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const productId = String(btn.dataset.productId || '');
            if (!productId || !productSelect) return;

            if (productSelect.tomselect) {
                productSelect.tomselect.setValue(productId, true);
            } else {
                productSelect.value = productId;
            }
            loadTiersForProduct(productId, { preferExisting: true, scroll: true });
        });
    });
})();
</script>
