@extends('layouts.app')

@section('title', 'Precios masivos')

@section('content')
<div class="topbar">
    <div>
        <h1>Edición masiva de precios</h1>
        <p class="muted">Venta, mayoreo y promo · montos c/IVA · IVA {{ number_format($vatRate * 100, 0) }}%</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('inventory.products.index') }}">Volver a productos</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('inventory.products.bulk-prices') }}">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por código o nombre">
        <label style="display:flex;align-items:center;gap:.4rem;white-space:nowrap">
            <input type="checkbox" name="show_inactive" value="1" @checked(request()->boolean('show_inactive'))>
            Ver inactivos
        </label>
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

@if ($products->isEmpty())
    <div class="card"><p class="muted" style="margin:0">No hay productos para mostrar.</p></div>
@else
    <form method="POST" action="{{ route('inventory.products.bulk-prices.update', array_filter(['q' => request('q'), 'show_inactive' => request('show_inactive') ?: null])) }}" id="bulk-prices-form">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="topbar" style="margin-bottom:.75rem">
                <div>
                    <strong>{{ $products->count() }} producto{{ $products->count() === 1 ? '' : 's' }}</strong>
                    <p class="muted" style="margin:.25rem 0 0">Edita las celdas y guarda todo de una vez. Solo se actualizan precios (no proveedores ni stock).</p>
                </div>
                <div class="actions">
                    <button class="btn" type="submit">Guardar precios</button>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Venta c/IVA *</th>
                        <th>Mayoreo c/IVA</th>
                        <th>Promo</th>
                        <th>Tipo</th>
                        <th>Valor promo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $index => $product)
                        @php
                            $old = old('products.'.$index);
                            $saleVat = $old['sale_price_with_vat'] ?? number_format($product->salePriceWithVat(), 2, '.', '');
                            $wholesaleVat = array_key_exists('wholesale_price_with_vat', $old ?? [])
                                ? ($old['wholesale_price_with_vat'] ?? '')
                                : ($product->wholesale_price_without_vat !== null
                                    ? number_format($product->wholesalePriceWithVat(), 2, '.', '')
                                    : '');
                            $promoActive = array_key_exists('promo_active', $old ?? [])
                                ? filter_var($old['promo_active'] ?? false, FILTER_VALIDATE_BOOLEAN)
                                : (bool) $product->promo_active;
                            $promoType = $old['promo_type'] ?? ($product->promo_type ?: 'amount');
                            $promoValue = $old['promo_value'] ?? ($product->promo_value !== null
                                ? number_format((float) $product->promo_value, 2, '.', '')
                                : '');
                        @endphp
                        <tr data-bulk-row>
                            <td>
                                <input type="hidden" name="products[{{ $index }}][id]" value="{{ $product->id }}">
                                <strong>{{ $product->code }}</strong>
                                <div>{{ $product->name }}</div>
                                @unless ($product->is_active)
                                    <span class="badge badge-off">Inactivo</span>
                                @endunless
                                @if ($product->promo_active)
                                    <div class="muted" style="margin-top:.25rem;font-size:.8rem">
                                        Efectivo ahora: {{ money($product->effectiveSalePriceWithVat()) }}
                                    </div>
                                @endif
                            </td>
                            <td style="min-width:7.5rem">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    name="products[{{ $index }}][sale_price_with_vat]"
                                    value="{{ $saleVat }}"
                                    required
                                    style="margin:0"
                                >
                            </td>
                            <td style="min-width:7.5rem">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    name="products[{{ $index }}][wholesale_price_with_vat]"
                                    value="{{ $wholesaleVat }}"
                                    placeholder="—"
                                    style="margin:0"
                                >
                            </td>
                            <td>
                                <input type="hidden" name="products[{{ $index }}][promo_active]" value="0">
                                <label style="display:flex;align-items:center;gap:.35rem;margin:0;font-weight:500">
                                    <input
                                        type="checkbox"
                                        name="products[{{ $index }}][promo_active]"
                                        value="1"
                                        @checked($promoActive)
                                        data-promo-toggle
                                    >
                                    Activa
                                </label>
                            </td>
                            <td style="min-width:8rem">
                                <select name="products[{{ $index }}][promo_type]" data-promo-type style="margin:0" @disabled(! $promoActive)>
                                    <option value="amount" @selected($promoType === 'amount')>Monto c/IVA</option>
                                    <option value="percent" @selected($promoType === 'percent')>% desc.</option>
                                </select>
                            </td>
                            <td style="min-width:7.5rem">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    name="products[{{ $index }}][promo_value]"
                                    value="{{ $promoValue }}"
                                    data-promo-value
                                    placeholder="—"
                                    style="margin:0"
                                    @disabled(! $promoActive)
                                >
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="actions" style="margin-top:1rem">
                <button class="btn" type="submit">Guardar precios</button>
                <a class="btn btn-secondary" href="{{ route('inventory.products.index') }}">Cancelar</a>
            </div>
        </div>
    </form>
@endif

<script>
(() => {
    const syncRow = (row) => {
        const toggle = row.querySelector('[data-promo-toggle]');
        const type = row.querySelector('[data-promo-type]');
        const value = row.querySelector('[data-promo-value]');
        const on = !!toggle?.checked;
        if (type) type.disabled = !on;
        if (value) value.disabled = !on;
    };

    document.querySelectorAll('[data-bulk-row]').forEach((row) => {
        syncRow(row);
        row.querySelector('[data-promo-toggle]')?.addEventListener('change', () => syncRow(row));
    });

    document.getElementById('bulk-prices-form')?.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.dataset.submitting === '1') {
            e.preventDefault();
            return;
        }
        form.dataset.submitting = '1';
        form.querySelectorAll('[type=submit]').forEach((btn) => {
            btn.disabled = true;
            btn.textContent = 'Guardando…';
        });
        // Re-enable disabled promo fields so values submit when active toggles changed mid-edit
        form.querySelectorAll('[data-bulk-row]').forEach((row) => {
            const on = row.querySelector('[data-promo-toggle]')?.checked;
            if (on) {
                row.querySelector('[data-promo-type]')?.removeAttribute('disabled');
                row.querySelector('[data-promo-value]')?.removeAttribute('disabled');
            }
        });
    });
})();
</script>
@endsection
