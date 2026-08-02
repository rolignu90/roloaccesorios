@php
    $priceGroups = $priceGroups ?? collect();
    $products = $products ?? collect();
@endphp

<div class="card" style="margin-top:1rem;border:2px solid var(--signal)">
    <div class="topbar" style="margin-bottom:.75rem">
        <div>
            <h2 style="margin:0;font-size:1.15rem">Precios especiales por producto</h2>
            <p class="muted" style="margin:.35rem 0 0">
                Define tramos por cantidad para este cliente (ej. desde 1, 3, 6, 9…).
                En ventas se aplica automáticamente el tramo según la cantidad.
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
                    <td>
                        <form method="POST" action="{{ route('sales.customers.prices.destroy', [$customer, $group['product']]) }}" onsubmit="return confirm('¿Eliminar precios de este producto para el cliente?')">
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
    <h2 style="margin-top:0;font-size:1.1rem">Agregar / actualizar precios de un producto</h2>
    <p class="muted">Ejemplo: desde 1 → $18, desde 3 → $16, desde 6 → $14. Si el producto ya tiene tramos, se reemplazan.</p>
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
                    ['min_quantity' => 3, 'unit_price_with_vat' => ''],
                    ['min_quantity' => 6, 'unit_price_with_vat' => ''],
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
            <button type="submit" class="btn">Guardar precios</button>
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
    if (!rows || !template) return;
    const reindex = () => {
        [...rows.querySelectorAll('[data-tier-row]')].forEach((row, index) => {
            row.querySelectorAll('input').forEach((el) => {
                if (el.name) el.name = el.name.replace(/tiers\[\d+]/, `tiers[${index}]`);
            });
        });
    };
    document.getElementById('add-tier')?.addEventListener('click', () => {
        const index = rows.querySelectorAll('[data-tier-row]').length;
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
        reindex();
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
    document.getElementById('product_id')?.addEventListener('change', (e) => {
        const opt = e.target.selectedOptions[0];
        const price = opt?.dataset?.wholesale || opt?.dataset?.sale || '';
        const firstPrice = rows.querySelector('[data-tier-row] input[name*="unit_price_with_vat"]');
        if (firstPrice && !firstPrice.value && price) firstPrice.value = price;
    });
})();
</script>
