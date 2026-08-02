@extends('layouts.app')

@section('title', 'Nueva consignación')

@section('content')
@php
    $oldItems = old('items', [['product_id' => '', 'quantity' => 1, 'unit_price_with_vat' => '']]);
    $partyType = old('party_type', 'seller');
@endphp

<div class="topbar">
    <div>
        <h1>Nueva consignación</h1>
        <p class="muted">Número tentativo: {{ $nextNumber }} · Descuenta stock FIFO</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('consignments.index') }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('consignments.store') }}" id="consignment-form">
        @csrf

        <div class="field">
            <label>Consignatario</label>
            <div style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:.4rem">
                <label style="display:flex;align-items:center;gap:.4rem;font-weight:500">
                    <input type="radio" name="party_type" value="seller" @checked($partyType === 'seller') data-party-type>
                    Vendedor
                </label>
                <label style="display:flex;align-items:center;gap:.4rem;font-weight:500">
                    <input type="radio" name="party_type" value="customer" @checked($partyType === 'customer') data-party-type>
                    Cliente
                </label>
            </div>
        </div>

        <div class="grid-2">
            <div class="field" id="seller-panel" @style(['display:none' => $partyType !== 'seller'])>
                <label for="seller_id">Vendedor *</label>
                <select id="seller_id" name="seller_id">
                    <option value="">— Selecciona —</option>
                    @foreach ($sellers as $seller)
                        <option value="{{ $seller->id }}" @selected(old('seller_id') == $seller->id)>
                            {{ $seller->sale_prefix ?: '' }}{{ $seller->code }} — {{ $seller->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field" id="customer-panel" @style(['display:none' => $partyType !== 'customer'])>
                <label for="customer_id">Cliente *</label>
                <select id="customer_id" name="customer_id">
                    <option value="">— Selecciona —</option>
                    @forelse ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>
                            {{ $customer->code }} — {{ $customer->name }}
                        </option>
                    @empty
                        <option value="" disabled>No hay clientes activos</option>
                    @endforelse
                </select>
            </div>
            <div class="field">
                <label for="delivered_at">Fecha de entrega *</label>
                <input id="delivered_at" type="datetime-local" name="delivered_at" value="{{ old('delivered_at', now()->format('Y-m-d\\TH:i')) }}" required>
            </div>
        </div>

        <div class="card" style="margin:1rem 0;padding:1rem;background:#f9fafb">
            <div class="topbar" style="margin-bottom:.75rem">
                <strong>Productos</strong>
                <button type="button" class="btn btn-secondary" id="add-item-row">Agregar línea</button>
            </div>
            <div id="item-rows">
                @foreach ($oldItems as $index => $item)
                    <div class="grid-3 item-row" data-row style="align-items:end">
                        <div class="field">
                            <label>Producto</label>
                            <select name="items[{{ $index }}][product_id]" data-product required>
                                <option value="">— Selecciona —</option>
                                @foreach ($products as $product)
                                    <option
                                        value="{{ $product->id }}"
                                        data-price="{{ $product->wholesalePriceWithVat() !== null ? number_format($product->wholesalePriceWithVat(), 2, '.', '') : '' }}"
                                        data-sale-price="{{ number_format($product->salePriceWithVat(), 2, '.', '') }}"
                                        @selected(($item['product_id'] ?? '') == $product->id)
                                    >
                                        {{ $product->code }} — {{ $product->name }}
                                        @if ($product->wholesalePriceWithVat() !== null)
                                            (reventa {{ money($product->wholesalePriceWithVat()) }})
                                        @endif
                                        (stock {{ (int) ($product->stock_on_hand ?? 0) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Cantidad</label>
                            <input type="number" min="1" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" data-qty required>
                        </div>
                        <div class="field">
                            <label>Precio c/IVA</label>
                            <input type="number" min="0" step="0.01" name="items[{{ $index }}][unit_price_with_vat]" value="{{ $item['unit_price_with_vat'] ?? '' }}" data-price-input required>
                            <p class="muted" style="margin:.25rem 0 0;font-size:.78rem" data-price-hint></p>
                        </div>
                        <div class="field" style="display:flex;align-items:flex-end;padding-bottom:.4rem">
                            <button type="button" class="btn-link remove-item-row" style="color:#b91c1c;border:0;background:transparent;cursor:pointer">Quitar</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="field">
            <label for="notes">Notas</label>
            <input id="notes" type="text" name="notes" value="{{ old('notes') }}">
        </div>

        <button class="btn" type="submit">Registrar consignación</button>
    </form>
</div>

<template id="item-row-template">
    <div class="grid-3 item-row" data-row style="align-items:end">
        <div class="field">
            <label>Producto</label>
            <select name="items[__INDEX__][product_id]" data-product required>
                <option value="">— Selecciona —</option>
                @foreach ($products as $product)
                    <option
                        value="{{ $product->id }}"
                        data-price="{{ $product->wholesalePriceWithVat() !== null ? number_format($product->wholesalePriceWithVat(), 2, '.', '') : '' }}"
                        data-sale-price="{{ number_format($product->salePriceWithVat(), 2, '.', '') }}"
                    >
                        {{ $product->code }} — {{ $product->name }}
                        @if ($product->wholesalePriceWithVat() !== null)
                            (reventa {{ money($product->wholesalePriceWithVat()) }})
                        @endif
                        (stock {{ (int) ($product->stock_on_hand ?? 0) }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>Cantidad</label>
            <input type="number" min="1" name="items[__INDEX__][quantity]" value="1" data-qty required>
        </div>
        <div class="field">
            <label>Precio c/IVA</label>
            <input type="number" min="0" step="0.01" name="items[__INDEX__][unit_price_with_vat]" value="" data-price-input required>
            <p class="muted" style="margin:.25rem 0 0;font-size:.78rem" data-price-hint></p>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;padding-bottom:.4rem">
            <button type="button" class="btn-link remove-item-row" style="color:#b91c1c;border:0;background:transparent;cursor:pointer">Quitar</button>
        </div>
    </div>
</template>

<script>
(() => {
    const customerPriceTiers = @json($customerPriceTiers ?? []);
    const productPriceDefaults = @json($productPriceDefaults ?? []);
    const rows = document.getElementById('item-rows');
    const template = document.getElementById('item-row-template');
    const sellerPanel = document.getElementById('seller-panel');
    const customerPanel = document.getElementById('customer-panel');
    const sellerSelect = document.getElementById('seller_id');
    const customerSelect = document.getElementById('customer_id');

    const currentCustomerId = () => {
        const type = document.querySelector('[data-party-type]:checked')?.value || 'seller';
        if (type !== 'customer') return null;
        const id = customerSelect?.value;
        return id ? String(id) : null;
    };

    const resolvePrice = (productId, qty) => {
        const pid = String(productId || '');
        const quantity = Math.max(1, Number(qty) || 1);
        const customerId = currentCustomerId();
        const tiers = customerId ? (customerPriceTiers[customerId]?.[pid] || []) : [];
        if (tiers.length) {
            let best = null;
            tiers.forEach((tier) => {
                if (Number(tier.min) <= quantity && (!best || Number(tier.min) > Number(best.min))) {
                    best = tier;
                }
            });
            if (best) {
                return { price: Number(best.price), source: 'cliente (≥' + best.min + ')' };
            }
        }
        const defaults = productPriceDefaults[pid] || {};
        if (defaults.wholesale !== null && defaults.wholesale !== undefined && defaults.wholesale !== '') {
            return { price: Number(defaults.wholesale), source: 'mayoreo' };
        }
        return { price: Number(defaults.sale || 0), source: 'venta' };
    };

    const applyRowPrice = (row, force = false) => {
        if (!row) return;
        const productSelect = row.querySelector('[data-product]');
        const qtyInput = row.querySelector('[data-qty]');
        const priceInput = row.querySelector('[data-price-input]');
        const hint = row.querySelector('[data-price-hint]');
        const productId = productSelect?.value;
        if (!priceInput || !productId) return;
        if (!force && priceInput.dataset.manual === '1') {
            if (hint) hint.textContent = 'Precio manual';
            return;
        }
        const resolved = resolvePrice(productId, qtyInput?.value || 1);
        priceInput.value = Number(resolved.price || 0).toFixed(2);
        priceInput.dataset.manual = '';
        if (hint) hint.textContent = 'Auto: ' + resolved.source;
    };

    const applyAllRowPrices = (force = false) => {
        rows?.querySelectorAll('[data-row]').forEach((row) => applyRowPrice(row, force));
    };

    const setSelectActive = (el, active) => {
        if (!el) return;
        el.required = active;
        el.disabled = !active;
        if (!active) el.value = '';
        if (el.tomselect) {
            if (active) el.tomselect.enable();
            else {
                el.tomselect.clear(true);
                el.tomselect.disable();
            }
        }
    };

    const syncParty = () => {
        const type = document.querySelector('[data-party-type]:checked')?.value || 'seller';
        const isSeller = type === 'seller';
        if (sellerPanel) sellerPanel.style.display = isSeller ? '' : 'none';
        if (customerPanel) customerPanel.style.display = isSeller ? 'none' : '';
        setSelectActive(sellerSelect, isSeller);
        setSelectActive(customerSelect, !isSeller);
        const visible = isSeller ? sellerSelect : customerSelect;
        if (visible?.tomselect) visible.tomselect.refreshOptions(false);
        else if (visible) window.initSearchableSelects?.(visible);
        applyAllRowPrices(true);
    };

    document.querySelectorAll('[data-party-type]').forEach((el) => {
        el.addEventListener('change', syncParty);
    });
    customerSelect?.addEventListener('change', () => applyAllRowPrices(true));

    const bootParty = () => syncParty();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setTimeout(bootParty, 0));
    } else {
        setTimeout(bootParty, 0);
    }

    const reindex = () => {
        [...rows.querySelectorAll('[data-row]')].forEach((row, index) => {
            row.querySelectorAll('select, input').forEach((el) => {
                if (el.name) el.name = el.name.replace(/items\[\d+]/, `items[${index}]`);
            });
        });
    };

    document.getElementById('add-item-row')?.addEventListener('click', () => {
        const index = rows.querySelectorAll('[data-row]').length;
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
        window.initSearchableSelects?.(rows.querySelector('[data-row]:last-child'));
        reindex();
    });

    rows?.addEventListener('click', (e) => {
        if (e.target.closest('.remove-item-row')) {
            const row = e.target.closest('[data-row]');
            if (rows.querySelectorAll('[data-row]').length > 1) {
                row.querySelectorAll('select').forEach((el) => window.destroySearchableSelect?.(el));
                row.remove();
                reindex();
            }
        }
    });

    rows?.addEventListener('searchable:change', (e) => {
        if (e.target.matches?.('[data-product]')) {
            applyRowPrice(e.target.closest('[data-row]'), true);
        }
    });
    rows?.addEventListener('change', (e) => {
        if (e.target.matches('[data-product]')) {
            applyRowPrice(e.target.closest('[data-row]'), true);
        }
        if (e.target.matches('[data-qty]')) {
            applyRowPrice(e.target.closest('[data-row]'), false);
        }
    });

    rows?.addEventListener('input', (e) => {
        if (e.target.matches('[data-price-input]')) {
            e.target.dataset.manual = '1';
            const hint = e.target.closest('[data-row]')?.querySelector('[data-price-hint]');
            if (hint) hint.textContent = 'Precio manual';
        }
        if (e.target.matches('[data-qty]')) {
            applyRowPrice(e.target.closest('[data-row]'), false);
        }
    });
})();
</script>
@endsection
