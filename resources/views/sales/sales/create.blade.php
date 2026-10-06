@extends('layouts.app')

@section('title', 'Nueva venta')

@section('content')
@php
    $customerMode = old('customer_mode', request('customer_id') ? 'existing' : 'new');
    $oldItems = collect(old('items', [['product_id' => '', 'quantity' => 1, 'unit_price_with_vat' => '', 'discount_percent' => 0, 'discount_amount' => 0]]))
        ->map(function (array $item) {
            if (! isset($item['unit_price_with_vat']) && isset($item['unit_price_without_vat'])) {
                $item['unit_price_with_vat'] = price_with_vat($item['unit_price_without_vat']);
            }

            return $item;
        })
        ->all();
@endphp

<div class="topbar">
    <div>
        <h1>Nueva venta</h1>
        <p class="muted">Número tentativo: <span id="next-sale-number">{{ $nextNumber }}</span> · IVA {{ number_format($vatRate * 100, 0) }}%</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('sales.sales.index') }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('sales.sales.store') }}" id="sale-form">
        @csrf

        <div class="field" style="margin-bottom:1rem">
            <label>Cliente</label>
            <div style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:.4rem">
                <label style="display:flex;align-items:center;gap:.4rem;font-weight:500">
                    <input type="radio" name="customer_mode" value="existing" @checked($customerMode === 'existing') data-customer-mode>
                    Cliente existente
                </label>
                <label style="display:flex;align-items:center;gap:.4rem;font-weight:500">
                    <input type="radio" name="customer_mode" value="new" @checked($customerMode === 'new') data-customer-mode>
                    Crear cliente nuevo
                </label>
            </div>
            <p class="muted" style="margin:.4rem 0 0">Si no existe, créalo aquí: el código (CLI-####) se asigna solo.</p>
        </div>

        <div id="existing-customer-panel" class="field" @style(['display:none' => $customerMode === 'new'])>
            <label for="customer_id">Cliente *</label>
            <select id="customer_id" name="customer_id" data-placeholder="Buscar cliente…">
                <option value="">— Selecciona —</option>
                @foreach ($customers as $customer)
                    <option
                        value="{{ $customer->id }}"
                        data-phone="{{ $customer->normalizedPhone() }}"
                        @selected(old('customer_id', request('customer_id')) == $customer->id)
                        @if (isset($returnRiskByCustomer[(string) $customer->id]))
                            data-has-returns="1"
                        @endif
                    >
                        {{ $customer->code }} — {{ $customer->name }}
                        @if (isset($returnRiskByCustomer[(string) $customer->id]))
                            · ⚠ devolución
                        @endif
                    </option>
                @endforeach
            </select>
            <div
                id="return-risk-alert-existing"
                class="flash"
                style="display:none;background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-top:.55rem;margin-bottom:0"
                role="alert"
            ></div>
            <div
                id="open-sale-alert-existing"
                class="flash"
                style="display:none;background:#fffbeb;color:#92400e;border-color:#fde68a;margin-top:.55rem;margin-bottom:0"
                role="alert"
            ></div>
            <p id="return-risk-phone-existing" class="muted" style="display:none;margin:.4rem 0 0;font-size:.85rem"></p>
        </div>

        <div id="new-customer-panel" class="card" style="margin:0 0 1rem;padding:1rem;background:#f9fafb;@if($customerMode !== 'new') display:none @endif">
            <strong style="display:block;margin-bottom:.75rem">Datos del nuevo cliente</strong>
            @include('sales.customers._form', [
                'namePrefix' => 'new_customer',
                'customer' => null,
                'forceEmptyName' => true,
                'showCode' => true,
                'showActive' => false,
                'compact' => true,
                'nextCode' => \App\Models\Customer::nextCode(),
                'phoneAlertId' => 'return-risk-alert-new',
                'phoneDuplicateAlertId' => 'open-sale-alert-new',
                'phonePrefillHintId' => 'phone-prefill-hint',
            ])
        </div>

        <div class="grid-2">
            <div class="field">
                <label for="sold_at">Fecha *</label>
                <input id="sold_at" type="datetime-local" name="sold_at" value="{{ old('sold_at', now()->format('Y-m-d\\TH:i')) }}" required>
            </div>
            <div class="field">
                <label for="seller_id">Vendedor *</label>
                <select id="seller_id" name="seller_id" required data-placeholder="Buscar vendedor…">
                    <option value="">— Selecciona —</option>
                    @forelse ($sellers as $seller)
                        <option
                            value="{{ $seller->id }}"
                            data-next-number="{{ $seller->next_sale_number }}"
                            @selected(old('seller_id', request('seller_id', auth()->user()->seller_id)) == $seller->id)
                        >
                            {{ $seller->sale_prefix ?: 'V-' }} {{ $seller->code }} — {{ $seller->name }}
                        </option>
                    @empty
                        <option value="" disabled>No hay vendedores activos</option>
                    @endforelse
                </select>
                <p class="muted" style="margin:.35rem 0 0">Puedes cambiar el vendedor antes de guardar; el número de venta se actualiza arriba.</p>
                @if ($sellers->isEmpty())
                    <p class="muted" style="margin:.35rem 0 0">
                        <a href="{{ route('sales.sellers.create') }}">Crea un vendedor</a> antes de registrar la venta.
                    </p>
                @endif
            </div>
        </div>

        <div class="field">
            <label for="payment_method">Método de pago *</label>
            <select id="payment_method" name="payment_method" required>
                @foreach ($paymentMethods as $key => $label)
                    <option value="{{ $key }}" @selected(old('payment_method', request('payment_method', 'cash')) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="card" style="margin:1rem 0;padding:1rem;background:#f9fafb">
            <div class="topbar" style="margin-bottom:.75rem">
                <strong>Productos y combos</strong>
                <button type="button" class="btn btn-secondary" id="add-item-row">Agregar línea</button>
            </div>
            <p class="muted" style="margin:0 0 1rem">El mismo listado incluye productos y combos. Si eliges un combo, se expanden sus ítems; en la primera línea puedes cambiar el combo, la <strong>cantidad de combos</strong> o elegir un producto.</p>

            @if (($quickPickProducts ?? collect())->isNotEmpty())
                <div style="margin:0 0 1rem;padding:.85rem 1rem;border:1px dashed #cbd5e1;border-radius:10px;background:#fff">
                    <div style="display:flex;justify-content:space-between;gap:.75rem;flex-wrap:wrap;align-items:baseline;margin-bottom:.65rem">
                        <strong style="font-size:.95rem">Acceso rápido</strong>
                        <span class="muted" style="font-size:.8rem">Favoritos, combos y productos más vendidos. Un clic agrega la línea.</span>
                    </div>
                    <div id="quick-picks" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.5rem">
                        @foreach ($quickPickProducts as $pick)
                            @php
                                $pickStyle = match ($pick->kind) {
                                    'favorite' => 'border-color:#f59e0b;background:#fffbeb;',
                                    'combo' => 'border-color:#6366f1;background:#eef2ff;',
                                    default => '',
                                };
                            @endphp
                            <button
                                type="button"
                                class="btn btn-secondary"
                                data-quick-pick="{{ $pick->value }}"
                                style="
                                    display:flex;flex-direction:column;align-items:flex-start;gap:.15rem;
                                    text-align:left;height:auto;white-space:normal;line-height:1.25;
                                    padding:.55rem .7rem;font-size:.82rem;
                                    {{ $pickStyle }}
                                "
                            >
                                <span style="display:flex;align-items:center;gap:.35rem;font-weight:700;flex-wrap:wrap">
                                    @if ($pick->kind === 'favorite')
                                        <span aria-hidden="true">★</span>
                                    @elseif ($pick->kind === 'combo')
                                        <span style="font-size:.7rem;font-weight:700;color:#4338ca;background:#c7d2fe;border-radius:999px;padding:.1rem .4rem">COMBO</span>
                                    @else
                                        <span class="muted" aria-hidden="true">#</span>
                                    @endif
                                    {{ $pick->code }}
                                    @if ($pick->kind === 'top' && $pick->qty_sold)
                                        <span class="muted" style="font-weight:500;font-size:.75rem">· {{ $pick->qty_sold }} uds</span>
                                    @elseif ($pick->kind === 'combo' && $pick->qty_sold)
                                        <span class="muted" style="font-weight:500;font-size:.75rem">· {{ $pick->qty_sold }} ventas</span>
                                    @endif
                                </span>
                                <span style="font-weight:500;color:#334155">{{ $pick->name }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div id="item-rows">
                @foreach ($oldItems as $index => $item)
                    <div class="grid-3 item-row" data-row style="align-items:end" @if(!empty($item['combo_id'])) data-combo-id="{{ $item['combo_id'] }}" @endif>
                        @if (!empty($item['combo_id']))
                            <input type="hidden" name="items[{{ $index }}][combo_id]" value="{{ $item['combo_id'] }}">
                        @endif
                        <div class="field">
                            <label>Producto / combo</label>
                            <select name="items[{{ $index }}][product_id]" data-product required @disabled(!empty($item['combo_id']))>
                                @include('sales.sales._catalog_options', [
                                    'products' => $products,
                                    'combos' => $combos ?? [],
                                    'selectedProductId' => $item['product_id'] ?? null,
                                ])
                            </select>
                            @if (!empty($item['combo_id']))
                                <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item['product_id'] }}">
                                <p class="muted" style="margin:.25rem 0 0;font-size:.78rem">Parte de un combo</p>
                            @endif
                        </div>
                        <div class="field">
                            <label>Cantidad</label>
                            <input type="number" min="1" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" data-qty required @readonly(!empty($item['combo_id']))>
                        </div>
                        <div class="field">
                            <label>Precio c/IVA (USD)</label>
                            <input type="number" min="0" step="0.01" name="items[{{ $index }}][unit_price_with_vat]" value="{{ $item['unit_price_with_vat'] ?? '' }}" data-price-input required @readonly(!empty($item['combo_id']))>
                            <p class="muted" style="margin:.25rem 0 0;font-size:.78rem" data-price-hint>{{ !empty($item['combo_id']) ? 'Precio de combo' : '' }}</p>
                        </div>
                        <div class="field">
                            <label>Desc. %</label>
                            <input type="number" min="0" max="100" step="0.01" name="items[{{ $index }}][discount_percent]" value="{{ $item['discount_percent'] ?? 0 }}" data-disc-pct @readonly(!empty($item['combo_id']))>
                        </div>
                        <div class="field">
                            <label>Desc. monto c/IVA</label>
                            <input type="number" min="0" step="0.01" name="items[{{ $index }}][discount_amount]" value="{{ $item['discount_amount'] ?? 0 }}" data-disc-amt @readonly(!empty($item['combo_id']))>
                        </div>
                        <div class="field" style="display:flex;align-items:flex-end;gap:.75rem;padding-bottom:.4rem">
                            <span class="muted" data-line-preview>—</span>
                            <button type="button" class="btn-link remove-item-row" style="color:#b91c1c;border:0;background:transparent;cursor:pointer">Quitar</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid-3">
            <div class="field">
                <label>Envío</label>
                <input type="hidden" name="has_shipping" value="0">
                <label style="display:flex;align-items:center;gap:.55rem;font-weight:500;margin-top:.45rem;cursor:pointer">
                    <input
                        type="checkbox"
                        name="has_shipping"
                        value="1"
                        @checked(old('has_shipping', '1') == '1' || old('has_shipping') === true || old('has_shipping') === 1)
                        data-has-shipping
                    >
                    Lleva envío
                </label>
                <p class="muted" style="margin:.35rem 0 0">Activo por defecto. Desmárcalo si es entrega local / sin courier.</p>
            </div>
            <div class="field" id="shipping-amount-field">
                <label for="shipping_amount">Envío cobrado al cliente (USD)</label>
                <input
                    id="shipping_amount"
                    type="number"
                    min="0"
                    step="0.01"
                    name="shipping_amount"
                    value="{{ old('shipping_amount', number_format($defaultShipping ?? 3, 2, '.', '')) }}"
                    data-shipping-input
                >
                <p class="muted" style="margin:.35rem 0 0">Puede ser distinto al costo de la empresa. Si hay producto con envío gratis, queda en $0.</p>
            </div>
            <div class="field" id="shipping-carrier-field">
                <label for="shipping_carrier_id">Empresa de envío *</label>
                @php
                    $selectedCarrierId = old(
                        'shipping_carrier_id',
                        request('shipping_carrier_id', $defaultShippingCarrierId ?? null)
                    );
                @endphp
                <select id="shipping_carrier_id" name="shipping_carrier_id" data-shipping-carrier>
                    <option value="">— Selecciona —</option>
                    @forelse ($shippingCarriers ?? [] as $carrier)
                        <option
                            value="{{ $carrier->id }}"
                            data-shipping-cost="{{ number_format((float) $carrier->shipping_cost, 2, '.', '') }}"
                            data-commission-type="{{ $carrier->commission_type }}"
                            data-commission-value="{{ number_format((float) $carrier->commission_value, 4, '.', '') }}"
                            @selected((string) $selectedCarrierId === (string) $carrier->id)
                        >
                            {{ $carrier->name }} — {{ $carrier->rateSummary() }}
                        </option>
                    @empty
                        <option value="" disabled>No hay empresas activas</option>
                    @endforelse
                </select>
                <p class="muted" style="margin:.35rem 0 0" id="carrier-cost-preview">Tu costo (envío + comisión COD) se calcula al guardar.</p>
                @if (($shippingCarriers ?? collect())->isEmpty())
                    <p class="muted" style="margin:.35rem 0 0">
                        <a href="{{ route('sales.shipping-carriers.create') }}">Crea una empresa de envío</a> antes de vender con courier.
                    </p>
                @endif
            </div>
            <div class="field">
                <label for="discount_percent">Descuento global %</label>
                <input id="discount_percent" type="number" min="0" max="100" step="0.01" name="discount_percent" value="{{ old('discount_percent', 0) }}">
            </div>
            <div class="field">
                <label for="discount_amount">Descuento global monto c/IVA (USD)</label>
                <input id="discount_amount" type="number" min="0" step="0.01" name="discount_amount" value="{{ old('discount_amount', 0) }}">
            </div>
        </div>
        <div class="field">
            <label for="notes">Notas</label>
            <input id="notes" type="text" name="notes" value="{{ old('notes') }}">
        </div>

        <div id="free-shipping-notice" class="flash" style="display:none;background:#fff7ed;color:#9a3412;border-color:#fdba74">
            Este producto tiene <strong>envío gratis</strong>. El precio de envío se estableció en <strong>$0.00</strong>.
        </div>

        <div class="meta" id="totals-preview">
            <div class="card">Subtotal s/IVA<strong data-subtotal>$0.00 USD</strong></div>
            <div class="card">Base gravada<strong data-base>$0.00 USD</strong></div>
            <div class="card">IVA {{ number_format($vatRate * 100, 0) }}%<strong data-vat>$0.00 USD</strong></div>
            <div class="card">Envío<strong data-shipping>$0.00 USD</strong></div>
            <div class="card">Total<strong data-total>$0.00 USD</strong></div>
        </div>

        <div class="actions" style="margin-top:1rem;display:flex;gap:.75rem;flex-wrap:wrap">
            <button class="btn" type="submit" name="after_save" value="show" id="confirm-sale-btn">
                Confirmar venta
            </button>
            <button class="btn btn-secondary" type="submit" name="after_save" value="new" id="confirm-sale-continue-btn">
                Confirmar y nueva venta
            </button>
        </div>
    </form>
</div>

<template id="item-row-template">
    <div class="grid-3 item-row" data-row style="align-items:end">
        <div class="field">
            <label>Producto / combo</label>
            <select name="items[__INDEX__][product_id]" data-product required>
                @include('sales.sales._catalog_options', [
                    'products' => $products,
                    'combos' => $combos ?? [],
                ])
            </select>
        </div>
        <div class="field">
            <label>Cantidad</label>
            <input type="number" min="1" name="items[__INDEX__][quantity]" value="1" data-qty required>
        </div>
        <div class="field">
            <label>Precio c/IVA (USD)</label>
            <input type="number" min="0" step="0.01" name="items[__INDEX__][unit_price_with_vat]" value="" data-price-input required>
            <p class="muted" style="margin:.25rem 0 0;font-size:.78rem" data-price-hint></p>
        </div>
        <div class="field">
            <label>Desc. %</label>
            <input type="number" min="0" max="100" step="0.01" name="items[__INDEX__][discount_percent]" value="0" data-disc-pct>
        </div>
        <div class="field">
            <label>Desc. monto c/IVA</label>
            <input type="number" min="0" step="0.01" name="items[__INDEX__][discount_amount]" value="0" data-disc-amt>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;gap:.75rem;padding-bottom:.4rem">
            <span class="muted" data-line-preview>—</span>
            <button type="button" class="btn-link remove-item-row" style="color:#b91c1c;border:0;background:transparent;cursor:pointer">Quitar</button>
        </div>
    </div>
</template>

@include('partials.sv-geo-script')

<script>
(() => {
    const vatRate = {{ (float) $vatRate }};
    const defaultShipping = {{ (float) ($defaultShipping ?? 3) }};
    const customerPriceTiers = @json($customerPriceTiers ?? []);
    const productPriceDefaults = @json($productPriceDefaults ?? []);
    const comboCatalog = @json($combos ?? []);
    const returnRiskByCustomer = @json($returnRiskByCustomer ?? []);
    const returnRiskByPhone = @json($returnRiskByPhone ?? []);
    const openSalesByCustomer = @json($openSalesByCustomer ?? []);
    const openSalesByPhone = @json($openSalesByPhone ?? []);
    const customerPhones = @json($customerPhones ?? []);
    const customersByPhone = @json($customersByPhone ?? []);
    const rows = document.getElementById('item-rows');
    const template = document.getElementById('item-row-template');
    const shippingInput = document.querySelector('[data-shipping-input]');
    const shippingAmountField = document.getElementById('shipping-amount-field');
    const shippingCarrierField = document.getElementById('shipping-carrier-field');
    const shippingCarrierSelect = document.querySelector('[data-shipping-carrier]');
    const carrierCostPreview = document.getElementById('carrier-cost-preview');
    const hasShippingToggle = document.querySelector('[data-has-shipping]');
    const freeShippingNotice = document.getElementById('free-shipping-notice');
    const returnRiskAlertExisting = document.getElementById('return-risk-alert-existing');
    const returnRiskAlertNew = document.getElementById('return-risk-alert-new');
    const openSaleAlertExisting = document.getElementById('open-sale-alert-existing');
    const openSaleAlertNew = document.getElementById('open-sale-alert-new');
    const returnRiskPhoneExisting = document.getElementById('return-risk-phone-existing');
    const phonePrefillHint = document.getElementById('phone-prefill-hint');
    const money = (n) => '$' + Number(n || 0).toFixed(2) + ' USD';
    let shippingManual = false;
    let lastPrefillPhone = '';
    const hasShippingEnabled = () => !!hasShippingToggle?.checked;
    const existingPanel = document.getElementById('existing-customer-panel');
    const newPanel = document.getElementById('new-customer-panel');
    const customerSelect = document.getElementById('customer_id');
    const newCustomerPhone = document.getElementById('new_customerphone');

    const normalizePhone = (value) => {
        const digits = String(value || '').replace(/\D+/g, '');
        if (!digits) return '';
        return digits.length >= 8 ? digits.slice(-8) : digits;
    };

    const setFieldValue = (id, value) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.value = value ?? '';
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const prefillNewCustomerFromPhone = () => {
        const mode = document.querySelector('[data-customer-mode]:checked')?.value || 'new';
        if (mode !== 'new') {
            if (phonePrefillHint) {
                phonePrefillHint.style.display = 'none';
                phonePrefillHint.textContent = '';
            }
            return;
        }

        const phone = normalizePhone(newCustomerPhone?.value);
        if (!phone || phone.length < 8) {
            lastPrefillPhone = '';
            if (phonePrefillHint) {
                phonePrefillHint.style.display = 'none';
                phonePrefillHint.textContent = '';
            }
            return;
        }

        const match = customersByPhone[phone];
        if (!match) {
            lastPrefillPhone = '';
            if (phonePrefillHint) {
                phonePrefillHint.style.display = 'none';
                phonePrefillHint.textContent = '';
            }
            return;
        }

        if (lastPrefillPhone === phone) {
            return;
        }
        lastPrefillPhone = phone;

        setFieldValue('new_customername', match.name || '');
        const nameInput = document.getElementById('new_customername');
        if (nameInput) nameInput.dataset.userEdited = '1';

        setFieldValue('new_customerdocument_type', match.document_type || 'N/A');
        const docType = document.querySelector('#new-customer-panel [data-document-type]');
        if (docType) docType.dataset.userEdited = '1';
        window.syncCustomerDocumentFields?.();
        if ((match.document_type || 'N/A') !== 'N/A') {
            setFieldValue('new_customerdocument_number', match.document_number || '');
        }

        setFieldValue('new_customeremail', match.email || '');
        setFieldValue('new_customeraddress', match.address || '');
        setFieldValue('new_customercountry', match.country || 'El Salvador');
        setFieldValue('new_customernotes', match.notes || '');

        const geoRoot = document.querySelector('#new-customer-panel [data-geo-root]');
        if (window.setSvGeoValues && geoRoot) {
            window.setSvGeoValues(geoRoot, match.department || '', match.municipality || '');
            // Si el cliente tenía CP manual distinto, respétalo después del sync geo.
            if (match.postal_code) {
                const postal = document.getElementById('new_customerpostal_code');
                if (postal) {
                    postal.value = match.postal_code;
                    postal.dataset.manual = '1';
                }
            }
        } else {
            setFieldValue('new_customerpostal_code', match.postal_code || '');
        }

        if (phonePrefillHint) {
            phonePrefillHint.style.display = '';
            phonePrefillHint.textContent = `Datos cargados de ${match.code} — ${match.name}. Sigues creando cliente nuevo; edita la dirección si cambió.`;
        }
    };

    const setAlert = (el, message) => {
        if (!el) return;
        if (!message) {
            el.style.display = 'none';
            el.textContent = '';
            return;
        }
        el.style.display = '';
        el.textContent = message;
    };

    const formatOpenSaleLine = (sale) => {
        const parts = [
            sale.number || '—',
            sale.status_label || sale.status || '',
            sale.sold_at || '',
            sale.total != null ? money(sale.total) : '',
        ].filter(Boolean);
        return parts.join(' · ');
    };

    const openSaleMessage = (bucket, includeCustomer = false) => {
        if (!bucket || !bucket.count) return '';
        const sales = bucket.sales || [];
        const first = sales[0];
        let msg = `⚠ Posible venta duplicada: ya hay ${bucket.count} pedido(s) abierto(s) (confirmada / en ruta)`;
        if (includeCustomer && first?.customer) {
            msg += ` de ${first.customer}`;
        }
        msg += '.';
        if (first) {
            msg += ` Más reciente: ${formatOpenSaleLine(first)}.`;
        }
        if (sales.length > 1) {
            msg += ` Otras: ${sales.slice(1, 3).map((s) => s.number).join(', ')}.`;
        }
        msg += ' Revisa antes de crear otra.';
        return msg;
    };

    const showReturnRisk = (message, mode) => {
        if (mode === 'existing') {
            setAlert(returnRiskAlertExisting, message);
            setAlert(returnRiskAlertNew, '');
        } else {
            setAlert(returnRiskAlertNew, message);
            setAlert(returnRiskAlertExisting, '');
            if (returnRiskPhoneExisting) {
                returnRiskPhoneExisting.style.display = 'none';
                returnRiskPhoneExisting.textContent = '';
            }
        }
    };

    const showOpenSaleAlert = (message, mode) => {
        if (mode === 'existing') {
            setAlert(openSaleAlertExisting, message);
            setAlert(openSaleAlertNew, '');
        } else {
            setAlert(openSaleAlertNew, message);
            setAlert(openSaleAlertExisting, '');
        }
    };

    const checkOpenSales = () => {
        const mode = document.querySelector('[data-customer-mode]:checked')?.value || 'new';
        if (mode === 'existing') {
            const id = customerSelect?.value ? String(customerSelect.value) : '';
            if (!id) {
                showOpenSaleAlert('', 'existing');
                return;
            }
            const byId = openSalesByCustomer[id];
            const phone = customerPhones[id] || customerSelect?.selectedOptions?.[0]?.dataset?.phone || '';
            const byPhone = phone ? openSalesByPhone[phone] : null;

            if (byId) {
                showOpenSaleAlert(openSaleMessage(byId), 'existing');
                return;
            }
            if (byPhone) {
                showOpenSaleAlert(openSaleMessage(byPhone, true), 'existing');
                return;
            }
            showOpenSaleAlert('', 'existing');
            return;
        }

        const phone = normalizePhone(newCustomerPhone?.value);
        if (!phone || phone.length < 8) {
            showOpenSaleAlert('', 'new');
            return;
        }
        const byPhone = openSalesByPhone[phone];
        showOpenSaleAlert(byPhone ? openSaleMessage(byPhone, true) : '', 'new');
    };

    const checkReturnRisk = () => {
        const mode = document.querySelector('[data-customer-mode]:checked')?.value || 'new';
        if (mode === 'existing') {
            const id = customerSelect?.value ? String(customerSelect.value) : '';
            if (!id) {
                showReturnRisk('', 'existing');
                if (returnRiskPhoneExisting) {
                    returnRiskPhoneExisting.style.display = 'none';
                    returnRiskPhoneExisting.textContent = '';
                }
                return;
            }
            const byId = returnRiskByCustomer[id];
            const phone = customerPhones[id] || customerSelect?.selectedOptions?.[0]?.dataset?.phone || '';
            const byPhone = phone ? returnRiskByPhone[phone] : null;
            if (returnRiskPhoneExisting) {
                if (phone) {
                    returnRiskPhoneExisting.style.display = '';
                    returnRiskPhoneExisting.textContent = 'Teléfono: ' + phone + (byPhone || byId ? ' · historial de devolución' : '');
                } else {
                    returnRiskPhoneExisting.style.display = 'none';
                    returnRiskPhoneExisting.textContent = '';
                }
            }
            if (byId) {
                let msg = `⚠ Cliente con historial de devoluciones (${byId.count}). Última: ${byId.last_number || '—'} (${byId.last_at || '—'}).`;
                if (byPhone && byPhone.count > byId.count) {
                    msg += ` También hay ${byPhone.count} devolución(es) con el mismo teléfono.`;
                }
                msg += ' Revisa riesgo antes de COD.';
                showReturnRisk(msg, 'existing');
                return;
            }
            if (byPhone) {
                showReturnRisk(`⚠ Este teléfono ya tuvo ${byPhone.count} devolución(es) (última ${byPhone.last_number || '—'} · ${byPhone.last_at || '—'}). Revisa riesgo antes de COD.`, 'existing');
                return;
            }
            showReturnRisk('', 'existing');
            return;
        }

        const phone = normalizePhone(newCustomerPhone?.value);
        const byPhone = phone ? returnRiskByPhone[phone] : null;
        if (byPhone) {
            const names = (byPhone.names || []).slice(0, 2).join(', ');
            showReturnRisk(`⚠ Este teléfono ya tuvo ${byPhone.count} devolución(es)${names ? ' (' + names + ')' : ''}. Última: ${byPhone.last_number || '—'} (${byPhone.last_at || '—'}). Revisa riesgo antes de COD.`, 'new');
            return;
        }
        showReturnRisk('', 'new');
    };

    const checkPhoneAlerts = () => {
        checkReturnRisk();
        checkOpenSales();
    };

    const currentCustomerId = () => {
        const mode = document.querySelector('[data-customer-mode]:checked')?.value || 'new';
        if (mode !== 'existing') return null;
        const id = customerSelect?.value;
        return id ? String(id) : null;
    };
    const resolvePrice = (productId, qty, productSelect = null) => {
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
        const option = productSelect?.selectedOptions?.[0];
        const optionSale = option?.dataset?.price;
        const optionWholesale = option?.dataset?.wholesale;
        const sale = defaults.sale ?? optionSale;
        const wholesale = defaults.wholesale ?? optionWholesale;

        // Cliente nuevo / sin tramos: precio de venta (no mayoreo).
        if (sale !== null && sale !== undefined && sale !== '') {
            return { price: Number(sale), source: 'venta' };
        }
        if (wholesale !== null && wholesale !== undefined && wholesale !== '') {
            return { price: Number(wholesale), source: 'mayoreo' };
        }
        return { price: 0, source: 'venta' };
    };

    const applyRowPrice = (row, force = false) => {
        const productSelect = row.querySelector('[data-product]');
        const qtyInput = row.querySelector('[data-qty]');
        const priceInput = row.querySelector('[data-price-input]');
        const hint = row.querySelector('[data-price-hint]');
        if (!productSelect || !priceInput) return;
        const productId = productSelect.value;
        if (!productId || String(productId).startsWith('combo:')) {
            if (hint) hint.textContent = '';
            return;
        }
        if (!force && priceInput.dataset.manual === '1') {
            if (hint) hint.textContent = 'Precio manual';
            return;
        }
        const resolved = resolvePrice(productId, qtyInput?.value || 1, productSelect);
        priceInput.value = Number(resolved.price || 0).toFixed(2);
        priceInput.dataset.manual = '';
        if (hint) hint.textContent = 'Auto: ' + resolved.source;
    };

    const applyAllRowPrices = (force = false) => {
        rows?.querySelectorAll('[data-row]').forEach((row) => applyRowPrice(row, force));
    };

    const setDisabled = (root, disabled) => {
        root?.querySelectorAll('input, select, textarea').forEach((el) => {
            if (el.matches('[data-customer-mode]')) return;
            el.disabled = disabled;
            if (el.tagName === 'SELECT') {
                if (!el.tomselect && !disabled) {
                    window.initSearchableSelects?.(el);
                }
                if (el.tomselect) {
                    if (disabled) el.tomselect.disable();
                    else el.tomselect.enable();
                }
            }
        });
    };

    const syncCustomerMode = () => {
        const mode = document.querySelector('[data-customer-mode]:checked')?.value || 'new';
        const isNew = mode === 'new';
        if (existingPanel) existingPanel.style.display = isNew ? 'none' : '';
        if (newPanel) newPanel.style.display = isNew ? '' : 'none';
        setDisabled(existingPanel, isNew);
        setDisabled(newPanel, !isNew);
        if (customerSelect) {
            customerSelect.required = !isNew;
            if (isNew) {
                if (customerSelect.tomselect) customerSelect.tomselect.clear(true);
                else customerSelect.value = '';
            } else {
                window.initSearchableSelects?.(customerSelect);
                customerSelect.tomselect?.enable();
            }
        }
        window.initSvGeoCascades?.(document);
        window.syncCustomerDocumentFields?.();

        // Evitar autofill del navegador con "Cliente Demo".
        if (isNew) {
            const nameInput = document.getElementById('new_customername');
            if (nameInput && !nameInput.dataset.userEdited) {
                const value = (nameInput.value || '').trim().toLowerCase();
                if (value === '' || value === 'cliente demo') {
                    nameInput.value = '';
                }
            }
            const docType = document.querySelector('#new-customer-panel [data-document-type]');
            if (docType && !docType.dataset.userEdited) {
                docType.value = 'N/A';
            }
            window.syncCustomerDocumentFields?.();
        }
        applyAllRowPrices(true);
        recalc();
        checkPhoneAlerts();
    };

    document.querySelectorAll('[data-customer-mode]').forEach((el) => {
        el.addEventListener('change', syncCustomerMode);
    });
    document.getElementById('new_customername')?.addEventListener('input', (e) => {
        e.target.dataset.userEdited = '1';
    });
    document.querySelector('#new-customer-panel [data-document-type]')?.addEventListener('change', (e) => {
        e.target.dataset.userEdited = '1';
    });
    customerSelect?.addEventListener('change', () => {
        applyAllRowPrices(true);
        recalc();
        checkPhoneAlerts();
    });
    newCustomerPhone?.addEventListener('input', () => {
        checkPhoneAlerts();
        prefillNewCustomerFromPhone();
    });
    newCustomerPhone?.addEventListener('change', () => {
        checkPhoneAlerts();
        prefillNewCustomerFromPhone();
    });
    newCustomerPhone?.addEventListener('blur', prefillNewCustomerFromPhone);
    const nextSaleNumberEl = document.getElementById('next-sale-number');
    const sellerSelect = document.getElementById('seller_id');
    const syncNextSaleNumber = () => {
        if (!nextSaleNumberEl || !sellerSelect) return;
        const option = sellerSelect.selectedOptions[0];
        const next = option?.dataset?.nextNumber;
        if (next) nextSaleNumberEl.textContent = next;
    };
    sellerSelect?.addEventListener('change', syncNextSaleNumber);
    syncNextSaleNumber();

    const lineNetWithVat = (row) => {
        const qtyInput = row.querySelector('[data-qty]');
        const realQty = row.querySelector('[data-combo-real-qty]');
        const qty = Number(realQty?.value || qtyInput?.value || 0);
        const priceWithVat = Number(row.querySelector('[data-price-input]')?.value || 0);
        const discPct = Number(row.querySelector('[data-disc-pct]')?.value || 0);
        const discAmt = Number(row.querySelector('[data-disc-amt]')?.value || 0);
        const grossWithVat = qty * priceWithVat;
        const discount = Math.min(grossWithVat, (grossWithVat * discPct / 100) + discAmt);
        return Math.max(0, grossWithVat - discount);
    };

    const lineSubtotal = (row) => lineNetWithVat(row) / (1 + vatRate);

    const hasFreeShippingProduct = () => {
        return [...rows.querySelectorAll('[data-row]')].some((row) => {
            if (row.dataset.freeShipping === '1') return true;
            const select = row.querySelector('[data-product]');
            return select?.selectedOptions?.[0]?.dataset?.freeShipping === '1';
        });
    };

    const syncShippingFromProducts = (showNotice = false) => {
        if (!hasShippingEnabled()) {
            if (shippingInput) shippingInput.value = '0.00';
            if (freeShippingNotice) freeShippingNotice.style.display = 'none';
            return;
        }
        const free = hasFreeShippingProduct();
        if (free) {
            if (shippingInput) shippingInput.value = '0.00';
            if (freeShippingNotice) freeShippingNotice.style.display = '';
            if (showNotice && freeShippingNotice) {
                freeShippingNotice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        } else {
            if (freeShippingNotice) freeShippingNotice.style.display = 'none';
            if (shippingInput && !shippingManual) {
                shippingInput.value = Number(defaultShipping).toFixed(2);
            }
        }
    };

    const recalc = () => {
        let subtotal = 0;
        let subtotalWithVat = 0;
        [...rows.querySelectorAll('[data-row]')].forEach((row) => {
            const netWithVat = lineNetWithVat(row);
            const line = netWithVat / (1 + vatRate);
            subtotal += line;
            subtotalWithVat += netWithVat;
            const preview = row.querySelector('[data-line-preview]');
            if (preview) preview.textContent = money(netWithVat);
        });
        const globalPct = Number(document.getElementById('discount_percent')?.value || 0);
        const globalAmt = Number(document.getElementById('discount_amount')?.value || 0);
        const globalDiscount = Math.min(subtotalWithVat, (subtotalWithVat * globalPct / 100) + globalAmt);
        const netWithVat = Math.max(0, subtotalWithVat - globalDiscount);
        const base = netWithVat / (1 + vatRate);
        const vat = netWithVat - base;
        const shipping = hasShippingEnabled() ? Number(shippingInput?.value || 0) : 0;
        const total = netWithVat + shipping;
        document.querySelector('[data-subtotal]').textContent = money(subtotal);
        document.querySelector('[data-base]').textContent = money(base);
        document.querySelector('[data-vat]').textContent = money(vat);
        document.querySelector('[data-shipping]').textContent = money(shipping);
        document.querySelector('[data-total]').textContent = money(total);
        updateCarrierCostPreview();
    };

    const syncHasShippingUi = () => {
        const enabled = hasShippingEnabled();
        if (shippingAmountField) shippingAmountField.style.display = enabled ? '' : 'none';
        if (shippingCarrierField) shippingCarrierField.style.display = enabled ? '' : 'none';
        if (shippingInput) shippingInput.disabled = !enabled;
        if (shippingCarrierSelect) {
            shippingCarrierSelect.disabled = !enabled;
            shippingCarrierSelect.required = enabled;
            if (!enabled) shippingCarrierSelect.value = '';
        }
        if (!enabled) {
            shippingManual = false;
            if (shippingInput) shippingInput.value = '0.00';
            if (freeShippingNotice) freeShippingNotice.style.display = 'none';
            if (carrierCostPreview) carrierCostPreview.textContent = 'Sin empresa de envío.';
        } else {
            syncShippingFromProducts(false);
            updateCarrierCostPreview();
        }
        recalc();
    };

    const updateCarrierCostPreview = () => {
        if (!carrierCostPreview) return;
        if (!hasShippingEnabled()) {
            carrierCostPreview.textContent = 'Sin empresa de envío.';
            return;
        }
        const opt = shippingCarrierSelect?.selectedOptions?.[0];
        if (!opt?.value) {
            carrierCostPreview.textContent = 'Selecciona la empresa para ver tu costo (envío + COD).';
            return;
        }
        const shipCost = Number(opt.dataset.shippingCost || 0);
        const commissionType = opt.dataset.commissionType || 'fixed';
        const commissionValue = Number(opt.dataset.commissionValue || 0);
        // total estimado: productos c/IVA + envío cobrado
        let products = 0;
        rows?.querySelectorAll('[data-row]').forEach((row) => {
            products += lineNetWithVat(row);
        });
        const globalPct = Number(document.getElementById('discount_percent')?.value || 0);
        const globalAmt = Number(document.getElementById('discount_amount')?.value || 0);
        const globalDiscount = Math.min(products, (products * globalPct / 100) + globalAmt);
        const netProducts = Math.max(0, products - globalDiscount);
        const customerShipping = Number(shippingInput?.value || 0);
        const totalWithVat = netProducts + customerShipping;
        const commission = commissionType === 'percent'
            ? totalWithVat * (commissionValue / 100)
            : commissionValue;
        const totalCost = shipCost + commission;
        carrierCostPreview.textContent = 'Tu costo estimado: envío '
            + money(shipCost) + ' + COD ' + money(commission) + ' = ' + money(totalCost)
            + ' (sobre total c/IVA ' + money(totalWithVat) + ')';
    };

    const reindex = () => {
        [...rows.querySelectorAll('[data-row]')].forEach((row, index) => {
            row.querySelectorAll('select, input').forEach((el) => {
                if (el.name) el.name = el.name.replace(/items\[\d+]/, `items[${index}]`);
            });
        });
        recalc();
    };

    const insertBlankRowAt = (referenceNode = null) => {
        const index = rows.querySelectorAll('[data-row]').length;
        const html = template.innerHTML.replaceAll('__INDEX__', String(index));
        if (referenceNode) {
            referenceNode.insertAdjacentHTML('beforebegin', html);
            return referenceNode.previousElementSibling;
        }
        rows.insertAdjacentHTML('beforeend', html);
        return rows.querySelector('[data-row]:last-child');
    };

    const removeComboGroup = (groupId) => {
        const groupRows = [...rows.querySelectorAll(`[data-row][data-combo-group="${groupId}"]`)];
        const anchor = groupRows[groupRows.length - 1]?.nextElementSibling || null;
        groupRows.forEach((comboRow) => {
            comboRow.querySelectorAll('select').forEach((el) => window.destroySearchableSelect?.(el));
            comboRow.remove();
        });
        return anchor;
    };

    const expandComboSelection = (sourceRow, combo, comboQty) => {
        const label = `${combo.code} — ${combo.name}`;
        const groupId = 'c' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);
        const anchor = sourceRow.nextElementSibling;

        sourceRow.querySelectorAll('select').forEach((el) => window.destroySearchableSelect?.(el));
        sourceRow.remove();

        allocateComboLines(combo, comboQty).forEach((line, index) => {
            appendComboRow(line, combo.id, label, groupId, {
                isPicker: index === 0,
                insertBefore: anchor,
                comboQty,
            });
        });
        reindex();
        syncShippingFromProducts(true);
        recalc();
    };

    const rebuildComboGroup = (groupId, comboQty) => {
        const groupRows = [...rows.querySelectorAll(`[data-row][data-combo-group="${groupId}"]`)];
        if (!groupRows.length) return;
        const comboId = groupRows[0].dataset.comboId;
        const combo = comboCatalog.find((c) => String(c.id) === String(comboId));
        if (!combo) return;

        const qty = Math.max(1, Number(comboQty) || 1);
        if (qty > Number(combo.stock || 0) && !combo.on_demand) {
            if (!confirm(`Solo hay stock para ${combo.stock} combo(s). ¿Continuar?`)) {
                const pickerQty = groupRows[0].querySelector('[data-qty]');
                if (pickerQty) pickerQty.value = groupRows[0].dataset.comboQty || '1';
                return;
            }
        }

        const anchor = removeComboGroup(groupId);
        const temp = insertBlankRowAt(anchor);
        expandComboSelection(temp, combo, qty);
    };

    const onProductChanged = (select) => {
        const row = select?.closest('[data-row]');
        if (!row) return;

        const value = String(select.value || '');

        // Primera línea de un combo expandido: permite cambiar o limpiar el grupo.
        if (select.dataset.comboPicker === '1') {
            const groupId = row.dataset.comboGroup;
            const currentValue = `combo:${row.dataset.comboId || ''}`;
            if (value === currentValue) return;

            const anchor = removeComboGroup(groupId);
            const newRow = insertBlankRowAt(anchor);
            window.initSearchableSelects?.(newRow);
            reindex();

            if (!value) {
                syncShippingFromProducts(false);
                recalc();
                return;
            }

            const newSelect = newRow.querySelector('[data-product]');
            if (newSelect?.tomselect) {
                newSelect.tomselect.setValue(value, true);
            } else if (newSelect) {
                newSelect.value = value;
            }
            onProductChanged(newSelect);
            return;
        }

        if (value.startsWith('combo:')) {
            const comboId = value.slice(6);
            const combo = comboCatalog.find((c) => String(c.id) === String(comboId));
            const comboQty = Math.max(1, Number(row.querySelector('[data-qty]')?.value || 1));
            if (!combo) {
                select.value = '';
                if (select.tomselect) select.tomselect.clear(true);
                return;
            }
            if (comboQty > Number(combo.stock || 0) && !combo.on_demand) {
                if (!confirm(`Solo hay stock para ${combo.stock} combo(s). ¿Agregar de todos modos?`)) {
                    select.value = '';
                    if (select.tomselect) select.tomselect.clear(true);
                    return;
                }
            }

            expandComboSelection(row, combo, comboQty);
            return;
        }

        applyRowPrice(row, true);
        const justSelectedFree = select.selectedOptions?.[0]?.dataset?.freeShipping === '1';
        syncShippingFromProducts(justSelectedFree);
        recalc();
    };

    document.getElementById('add-item-row')?.addEventListener('click', () => {
        const index = rows.querySelectorAll('[data-row]').length;
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
        const newRow = rows.querySelector('[data-row]:last-child');
        window.initSearchableSelects?.(newRow);
        reindex();
    });

    const setCatalogValueOnRow = (row, catalogValue) => {
        const select = row?.querySelector('[data-product]');
        if (!select || select.disabled) return false;
        const value = String(catalogValue);
        if (select.tomselect) {
            select.tomselect.setValue(value, false);
        } else {
            select.value = value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            select.dispatchEvent(new CustomEvent('searchable:change', { bubbles: true }));
        }
        return true;
    };

    const quickAddCatalogItem = (catalogValue) => {
        if (!catalogValue || !rows || !template) return;

        const emptyRow = [...rows.querySelectorAll('[data-row]')].find((row) => {
            const select = row.querySelector('[data-product]');
            return select && !select.disabled && !select.value && !row.dataset.comboId;
        });

        if (emptyRow) {
            setCatalogValueOnRow(emptyRow, catalogValue);
            return;
        }

        document.getElementById('add-item-row')?.click();
        const newRow = rows.querySelector('[data-row]:last-child');
        requestAnimationFrame(() => setCatalogValueOnRow(newRow, catalogValue));
    };

    document.getElementById('quick-picks')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-quick-pick]');
        if (!btn) return;
        quickAddCatalogItem(btn.dataset.quickPick);
    });

    const allocateComboLines = (combo, comboQty) => {
        const components = combo.items || [];
        if (!components.length) return [];
        const qty = Math.max(1, Number(comboQty) || 1);
        const comboPrice = Number(combo.price || 0) * qty;
        let weights = components.map((item) => {
            const ref = Number(item.ref_price || 0);
            return Math.max(ref > 0 ? ref : 1, 0.01) * Number(item.quantity || 1);
        });
        let weightTotal = weights.reduce((a, b) => a + b, 0);
        if (weightTotal <= 0) {
            weights = components.map(() => 1);
            weightTotal = weights.length;
        }
        const lines = [];
        let allocated = 0;
        components.forEach((item, index) => {
            const componentQty = Math.max(1, Number(item.quantity || 1));
            const lineQty = componentQty * qty;
            let lineTotal;
            if (index === components.length - 1) {
                lineTotal = Math.round((comboPrice - allocated) * 100) / 100;
            } else {
                lineTotal = Math.round(comboPrice * (weights[index] / weightTotal) * 100) / 100;
                allocated = Math.round((allocated + lineTotal) * 100) / 100;
            }
            const unit = lineQty > 0 ? Math.round((lineTotal / lineQty) * 100) / 100 : 0;
            lines.push({
                product_id: String(item.product_id),
                quantity: lineQty,
                unit_price_with_vat: unit,
                free_shipping: (combo.free_shipping || item.free_shipping) ? '1' : '0',
                label: (item.code || '') + ' — ' + (item.name || ''),
            });
        });
        // Fix residual cents on last line.
        const sum = lines.reduce((acc, line) => acc + Math.round(line.unit_price_with_vat * line.quantity * 100) / 100, 0);
        const diff = Math.round((comboPrice - sum) * 100) / 100;
        if (diff !== 0 && lines.length) {
            const last = lines[lines.length - 1];
            if (last.quantity > 0) {
                const newTotal = Math.round((last.unit_price_with_vat * last.quantity + diff) * 100) / 100;
                last.unit_price_with_vat = Math.round((newTotal / last.quantity) * 100) / 100;
            }
        }
        return lines;
    };

    const appendComboRow = (line, comboId, comboLabel, groupId, options = {}) => {
        const { isPicker = false, insertBefore = null, comboQty = 1 } = options;
        const index = rows.querySelectorAll('[data-row]').length;
        const html = template.innerHTML.replaceAll('__INDEX__', String(index));
        if (insertBefore) {
            insertBefore.insertAdjacentHTML('beforebegin', html);
        } else {
            rows.insertAdjacentHTML('beforeend', html);
        }
        const row = insertBefore
            ? insertBefore.previousElementSibling
            : rows.querySelector('[data-row]:last-child');

        row.dataset.comboId = String(comboId);
        row.dataset.comboGroup = String(groupId);
        row.dataset.comboQty = String(Math.max(1, Number(comboQty) || 1));
        row.dataset.componentQty = String(Math.max(1, Math.round(Number(line.quantity) / Math.max(1, Number(comboQty) || 1))));
        row.dataset.freeShipping = line.free_shipping === '1' ? '1' : '0';

        const comboHidden = document.createElement('input');
        comboHidden.type = 'hidden';
        comboHidden.name = `items[${index}][combo_id]`;
        comboHidden.value = String(comboId);
        row.prepend(comboHidden);

        const productSelect = row.querySelector('[data-product]');
        const productField = productSelect.parentElement;
        const productHidden = document.createElement('input');
        productHidden.type = 'hidden';
        productHidden.name = `items[${index}][product_id]`;
        productHidden.value = line.product_id;

        if (isPicker) {
            // Select editable con el combo; el product_id real va en hidden.
            productSelect.removeAttribute('name');
            productSelect.required = false;
            productSelect.dataset.comboPicker = '1';
            productSelect.value = `combo:${comboId}`;
            productSelect.insertAdjacentElement('afterend', productHidden);
            const note = document.createElement('p');
            note.className = 'muted';
            note.style.cssText = 'margin:.25rem 0 0;font-size:.78rem';
            note.textContent = `Combo: ${comboLabel} · incluye ${line.label}`;
            productField.appendChild(note);
            window.initSearchableSelects?.(productSelect);
            if (productSelect.tomselect) {
                productSelect.tomselect.setValue(`combo:${comboId}`, true);
            }
        } else {
            window.destroySearchableSelect?.(productSelect);
            productSelect.remove();
            const labelEl = document.createElement('div');
            labelEl.style.cssText = 'padding:.55rem 0;font-weight:500';
            labelEl.textContent = line.label;
            productField.appendChild(labelEl);
            productField.appendChild(productHidden);
            const note = document.createElement('p');
            note.className = 'muted';
            note.style.cssText = 'margin:.25rem 0 0;font-size:.78rem';
            note.textContent = 'Parte del combo: ' + comboLabel;
            productField.appendChild(note);
        }

        const qty = row.querySelector('[data-qty]');
        const qtyLabel = qty?.closest('.field')?.querySelector('label');
        if (isPicker) {
            // Cantidad de combos (editable); al guardar se envía la cantidad del componente.
            qty.dataset.comboQtyInput = '1';
            qty.value = String(Math.max(1, Number(comboQty) || 1));
            qty.readOnly = false;
            if (qtyLabel) qtyLabel.textContent = 'Cant. combos';
            const qtyNote = document.createElement('p');
            qtyNote.className = 'muted';
            qtyNote.style.cssText = 'margin:.25rem 0 0;font-size:.78rem';
            qtyNote.textContent = `×${row.dataset.componentQty} en esta línea al guardar`;
            qty.closest('.field')?.appendChild(qtyNote);

            // Cantidad real del producto (oculta) para el POST.
            const realQty = document.createElement('input');
            realQty.type = 'hidden';
            realQty.name = qty.name;
            realQty.value = String(line.quantity);
            realQty.dataset.comboRealQty = '1';
            qty.removeAttribute('name');
            qty.insertAdjacentElement('afterend', realQty);
        } else {
            qty.value = line.quantity;
            qty.readOnly = true;
            if (qtyLabel) qtyLabel.textContent = 'Cantidad';
        }

        const price = row.querySelector('[data-price-input]');
        price.value = Number(line.unit_price_with_vat).toFixed(2);
        price.readOnly = true;
        price.dataset.manual = '1';
        const hint = row.querySelector('[data-price-hint]');
        if (hint) hint.textContent = 'Precio de combo';

        row.querySelector('[data-disc-pct]').readOnly = true;
        row.querySelector('[data-disc-amt]').readOnly = true;
    };

    rows?.addEventListener('click', (e) => {
        if (e.target.closest('.remove-item-row')) {
            const row = e.target.closest('[data-row]');
            const comboGroup = row?.dataset?.comboGroup;
            if (comboGroup) {
                [...rows.querySelectorAll(`[data-row][data-combo-group="${comboGroup}"]`)].forEach((comboRow) => {
                    comboRow.querySelectorAll('select').forEach((el) => window.destroySearchableSelect?.(el));
                    comboRow.remove();
                });
                if (!rows.querySelector('[data-row]')) {
                    document.getElementById('add-item-row')?.click();
                }
                reindex();
                syncShippingFromProducts(false);
                recalc();
                return;
            }
            if (rows.querySelectorAll('[data-row]').length > 1) {
                row.querySelectorAll('select').forEach((el) => window.destroySearchableSelect?.(el));
                row.remove();
                reindex();
                syncShippingFromProducts(false);
                recalc();
            }
        }
    });

    // Solo searchable:change (Tom Select). El change nativo también dispara y duplicaría combos.
    rows?.addEventListener('searchable:change', (e) => {
        if (e.target.matches?.('[data-product]')) {
            onProductChanged(e.target);
        }
    });
    rows?.addEventListener('change', (e) => {
        if (e.target.matches('[data-qty]')) {
            const row = e.target.closest('[data-row]');
            if (e.target.dataset.comboQtyInput === '1' && row?.dataset?.comboGroup) {
                rebuildComboGroup(row.dataset.comboGroup, e.target.value);
                return;
            }
            applyRowPrice(row, false);
            recalc();
        }
    });
    rows?.addEventListener('input', (e) => {
        if (e.target.matches('[data-qty]')) {
            if (e.target.dataset.comboQtyInput === '1') {
                return;
            }
            applyRowPrice(e.target.closest('[data-row]'), false);
        }
        if (e.target.matches('[data-price-input]')) {
            e.target.dataset.manual = '1';
            const hint = e.target.closest('[data-row]')?.querySelector('[data-price-hint]');
            if (hint) hint.textContent = 'Precio manual';
        }
        recalc();
    });
    document.getElementById('discount_percent')?.addEventListener('input', recalc);
    document.getElementById('discount_amount')?.addEventListener('input', recalc);
    hasShippingToggle?.addEventListener('change', syncHasShippingUi);
    shippingCarrierSelect?.addEventListener('change', updateCarrierCostPreview);
    shippingInput?.addEventListener('input', () => {
        shippingManual = true;
        if (hasFreeShippingProduct() && shippingInput) {
            shippingInput.value = '0.00';
        }
        updateCarrierCostPreview();
        recalc();
    });

    // Boot: después de declarar recalc y helpers.
    syncCustomerMode();
    bindProductPriceHandlers(rows);
    setTimeout(() => bindProductPriceHandlers(rows), 0);
    syncHasShippingUi();

    document.getElementById('sale-form')?.addEventListener('submit', (e) => {
        const form = e.target;
        if (form?.dataset.submitting === '1') {
            e.preventDefault();
            return;
        }
        form.dataset.submitting = '1';

        const submitter = e.submitter;
        if (submitter?.name === 'after_save') {
            let hidden = form.querySelector('input[name="after_save"][data-forced]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'after_save';
                hidden.dataset.forced = '1';
                form.appendChild(hidden);
            }
            hidden.value = submitter.value;
        }

        ['confirm-sale-btn', 'confirm-sale-continue-btn'].forEach((id) => {
            const btn = document.getElementById(id);
            if (!btn) return;
            btn.disabled = true;
            if (submitter && btn === submitter) {
                btn.textContent = 'Guardando…';
            }
        });
    });
})();
</script>
@endsection
