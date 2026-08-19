@extends('layouts.app')

@section('title', $sale->number)

@section('content')
@php
    $customer = $sale->customer;
    $departments = \App\Support\ElSalvadorGeo::departmentNames();
    $selectedDepartment = old('department', $customer?->department);
    $municipalities = filled($selectedDepartment)
        ? \App\Support\ElSalvadorGeo::municipalityNames($selectedDepartment)
        : [];
    $canEdit = $sale->isConfirmed();
@endphp

<div class="topbar">
    <div>
        <h1>{{ $sale->number }}</h1>
        <p class="muted">
            {{ $sale->sold_at->format('d/m/Y H:i') }} ·
            {{ $customer?->name }} ·
            Vendedor: {{ $sale->seller?->name ?? '—' }} ·
            {{ config('sales.payment_methods')[$sale->payment_method] ?? $sale->payment_method }}
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('sales.sales.index') }}">Lista</a>
        @if ($customer)
            <a class="btn btn-secondary" href="{{ route('sales.customers.show', $customer) }}">Ver cliente</a>
        @endif
        @if ($sale->canSendToSistrack())
            <form method="POST" action="{{ route('sales.sales.send-sistrack.one', $sale) }}" id="sistrack-one-form">
                @csrf
                <button class="btn" type="submit" id="sistrack-one-btn">Enviar a Sistrack</button>
            </form>
        @endif
        @if ($sale->canSyncSistrackStatus())
            <form method="POST" action="{{ route('sales.sales.sync-sistrack-status.one', $sale) }}">
                @csrf
                <button class="btn btn-secondary" type="submit">Sincronizar estado</button>
            </form>
        @endif
        @if ($sale->isInTransit())
            <form method="POST" action="{{ route('sales.sales.mark-delivered.one', $sale) }}" onsubmit="return confirm('¿Marcar esta venta como entregada?')">
                @csrf
                <button class="btn" type="submit">Marcar entregada</button>
            </form>
        @endif
        @if (! $sale->isVoided() && $canEdit)
            <form method="POST" action="{{ route('sales.sales.void', $sale) }}" onsubmit="return confirm('¿Anular esta venta y restaurar stock?')">
                @csrf
                <button class="btn btn-danger" type="submit">Anular venta</button>
            </form>
        @elseif (! $sale->isVoided() && $sale->isInTransit())
            <form method="POST" action="{{ route('sales.sales.void', $sale) }}" onsubmit="return confirm('¿Anular esta venta y restaurar stock?')">
                @csrf
                <button class="btn btn-danger" type="submit">Anular venta</button>
            </form>
        @endif
    </div>
</div>

@if ($errors->has('sistrack') || $errors->has('status'))
    <div class="flash" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-bottom:1rem">
        {{ $errors->first('sistrack') ?: $errors->first('status') }}
    </div>
@endif

<div class="meta">
    <div class="card">
        Estado
        <strong>
            <span class="badge {{ $sale->statusBadgeClass() }}">
                {{ $sale->statusLabel() }}
            </span>
            @if ($sale->isStuckInTransit())
                <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500;color:#991b1b">En ruta ≥ 7 días</div>
            @endif
            @if ($sale->sistrack_shipping_status)
                <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500">Sistrack: {{ $sale->sistrack_shipping_status }}</div>
            @endif
        </strong>
    </div>
    <div class="card">
        Sistrack
        <strong>
            @if ($sale->has_shipping)
                <span class="badge {{ $sale->isSistrackSent() ? 'badge-ok' : ($sale->sistrack_status === 'failed' ? 'badge-off' : 'badge-warn') }}">
                    {{ $sale->sistrackStatusLabel() }}
                </span>
                @if ($sale->sistrack_external_id)
                    <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500">ID {{ $sale->sistrack_external_id }}</div>
                @endif
                @if ($sale->sistrack_last_error)
                    <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500;color:#991b1b">{{ $sale->sistrack_last_error }}</div>
                @endif
                @if ($sale->sistrack_status_synced_at)
                    <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500">Última sync {{ $sale->sistrack_status_synced_at->format('d/m/Y H:i') }}</div>
                @elseif ($sale->sistrack_last_attempt_at)
                    <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500">Último intento {{ $sale->sistrack_last_attempt_at->format('d/m/Y H:i') }}</div>
                @endif
            @else
                —
            @endif
        </strong>
    </div>
    <div class="card">Base s/IVA<strong>{{ money($sale->taxable_base) }}</strong></div>
    <div class="card">IVA {{ number_format($sale->vat_rate * 100, 0) }}%<strong>{{ money($sale->vat_amount) }}</strong></div>
    <div class="card">Productos c/IVA<strong>{{ money((float) $sale->total - (float) $sale->shipping_amount) }}</strong></div>
    <div class="card">Envío al cliente
        <strong>
            @if (! $sale->has_shipping)
                —
                <span class="badge badge-off" style="margin-left:.35rem">Sin envío</span>
            @elseif ((float) $sale->shipping_amount > 0)
                {{ money($sale->shipping_amount) }}
                <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500">Lo que paga el cliente</div>
            @else
                {{ money(0) }}
                <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500">Gratis para el cliente</div>
            @endif
        </strong>
    </div>
    <div class="card">Total a cobrar<strong>{{ money($sale->total) }}</strong></div>
    <div class="card">COGS FIFO<strong>{{ money($sale->cogs_total) }}</strong></div>
    <div class="card">Margen producto s/IVA<strong>{{ money($sale->grossMarginWithoutVat()) }}</strong>
        <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500">Sin envío</div>
    </div>
    <div class="card">Margen producto c/IVA<strong>{{ money($sale->grossMarginWithVat()) }}</strong>
        <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500">Sin envío</div>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="topbar" style="margin-bottom:.75rem">
        <div>
            <h2 style="margin:0;font-size:1.1rem">Cliente / entrega</h2>
            <p class="muted" style="margin:.35rem 0 0">
                Contra entrega: puedes corregir nombre y dirección antes de despachar.
                @if ($customer)
                    · {{ $customer->code }}
                @endif
            </p>
        </div>
    </div>

    @if ($canEdit && $customer)
        <form method="POST" action="{{ route('sales.sales.customer.update', $sale) }}" data-geo-root>
            @csrf
            @method('PATCH')
            <div class="grid-2">
                <div class="field">
                    <label for="customer_name">Nombre *</label>
                    <input id="customer_name" type="text" name="name" value="{{ old('name', $customer->name) }}" required autocomplete="off">
                </div>
                <div class="field">
                    <label for="customer_phone">Teléfono</label>
                    <input id="customer_phone" type="text" name="phone" value="{{ old('phone', $customer->phone) }}" autocomplete="off">
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="customer_email">Email</label>
                    <input id="customer_email" type="email" name="email" value="{{ old('email', $customer->email) }}" autocomplete="off">
                </div>
                <div class="field">
                    <label for="customer_address">Dirección</label>
                    <input id="customer_address" type="text" name="address" value="{{ old('address', $customer->address) }}" autocomplete="off">
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="customer_department">Departamento</label>
                    <select id="customer_department" name="department" data-geo-department data-searchable>
                        <option value="">— Selecciona —</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department }}" @selected(old('department', $customer->department) === $department)>{{ $department }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="customer_municipality">Municipio</label>
                    <select
                        id="customer_municipality"
                        name="municipality"
                        data-geo-municipality
                        data-searchable
                        data-selected="{{ old('municipality', $customer->municipality) }}"
                        @disabled(! filled(old('department', $customer->department)))
                    >
                        <option value="">— Selecciona —</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality }}" @selected(old('municipality', $customer->municipality) === $municipality)>{{ $municipality }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="customer_country">País</label>
                    <input id="customer_country" type="text" name="country" value="{{ old('country', $customer->country ?: 'El Salvador') }}">
                </div>
                <div class="field">
                    <label for="customer_postal_code">Código postal</label>
                    <input id="customer_postal_code" type="text" name="postal_code" value="{{ old('postal_code', $customer->postal_code) }}" data-geo-postal>
                </div>
            </div>
            <div class="actions">
                <button class="btn" type="submit">Guardar datos de entrega</button>
            </div>
        </form>
    @elseif ($customer)
        <div class="grid-2">
            <div><span class="muted">Nombre</span><div><strong>{{ $customer->name }}</strong></div></div>
            <div><span class="muted">Teléfono</span><div>{{ $customer->phone ?: '—' }}</div></div>
            <div><span class="muted">Dirección</span><div>{{ $customer->address ?: '—' }}</div></div>
            <div><span class="muted">Departamento</span><div>{{ $customer->department ?: '—' }}</div></div>
            <div><span class="muted">Municipio</span><div>{{ $customer->municipality ?: '—' }}</div></div>
            <div><span class="muted">País / CP</span><div>{{ $customer->country ?: '—' }} · {{ $customer->postal_code ?: '—' }}</div></div>
        </div>
    @else
        <p class="muted">Sin cliente asociado.</p>
    @endif
</div>

@if ($sale->has_shipping || $canEdit)
    <div class="card" style="margin-bottom:1rem">
        <h2 style="margin-top:0;font-size:1.1rem">Envío: cliente vs empresa</h2>
        <p class="muted" style="margin-top:0">El monto al cliente y el costo del courier son independientes; juntos dan el margen real.</p>
        @if ($sale->has_shipping)
            <div class="meta" style="margin:0 0 1rem">
                <div class="card">Empresa<strong>{{ $sale->shippingCarrier?->name ?? '—' }}</strong></div>
                <div class="card">Cobrado al cliente<strong>{{ money($sale->shipping_amount) }}</strong></div>
                <div class="card">Costo envío (empresa)<strong>{{ money($sale->carrier_shipping_cost) }}</strong></div>
                <div class="card">Comisión COD<strong>{{ money($sale->carrier_commission_amount) }}</strong></div>
                <div class="card">Costo total empresa<strong>{{ money($sale->carrierCostTotal()) }}</strong></div>
                <div class="card">Dif. envío (cliente − empresa)<strong>{{ money($sale->shippingSpread()) }}</strong></div>
                <div class="card">Margen real s/IVA<strong>{{ money($sale->realMarginWithoutVat()) }}</strong>
                    <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500">Producto + envío cliente − costos empresa</div>
                </div>
                <div class="card">Margen real c/IVA<strong>{{ money($sale->realMarginWithVat()) }}</strong>
                    <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500">Total cobrado − COGS − costos empresa</div>
                </div>
            </div>
        @endif

        @if ($canEdit)
            <form method="POST" action="{{ route('sales.sales.shipping.update', $sale) }}">
                @csrf
                @method('PATCH')
                <div class="grid-2">
                    <div class="field">
                        <label for="shipping_amount">Envío cobrado al cliente (USD)</label>
                        <input
                            id="shipping_amount"
                            type="number"
                            min="0"
                            step="0.01"
                            name="shipping_amount"
                            value="{{ old('shipping_amount', number_format((float) $sale->shipping_amount, 2, '.', '')) }}"
                            @disabled(! $sale->has_shipping)
                        >
                        @if (! $sale->has_shipping)
                            <p class="muted" style="margin:.35rem 0 0">Esta venta está marcada sin envío.</p>
                        @else
                            <p class="muted" style="margin:.35rem 0 0">Si hay producto con envío gratis, queda en $0 al recalcular.</p>
                        @endif
                    </div>
                    <div class="field">
                        <label for="sale_notes">Notas</label>
                        <textarea id="sale_notes" name="notes" rows="3">{{ old('notes', $sale->notes) }}</textarea>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn" type="submit">Guardar envío / notas</button>
                </div>
            </form>
        @elseif ($sale->notes)
            <p style="margin:0"><strong>Notas:</strong> {{ $sale->notes }}</p>
        @endif
    </div>
@elseif ($sale->notes)
    <div class="card" style="margin-bottom:1rem"><strong>Notas:</strong> {{ $sale->notes }}</div>
@endif

<div class="card" style="margin-bottom:1rem">
    <div class="topbar" style="margin-bottom:.75rem">
        <div>
            <h2 style="margin:0;font-size:1.1rem">Ítems</h2>
            <p class="muted" style="margin:.35rem 0 0">
                @if ($canEdit)
                    Puedes agregar, quitar o cambiar cantidades. El stock FIFO y la comisión COD se recalculan.
                @endif
            </p>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant.</th>
                <th>P. unit s/IVA</th>
                <th>Desc.</th>
                <th>Subtotal</th>
                <th>IVA</th>
                <th>Total</th>
                <th>COGS</th>
                @if ($canEdit)
                    <th></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>
                        {{ $item->product?->code }} — {{ $item->product?->name }}
                        @if ($item->combo)
                            <div class="muted" style="font-size:.8rem">Combo {{ $item->combo->code }} — {{ $item->combo->name }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($canEdit)
                            <form method="POST" action="{{ route('sales.sales.items.update', [$sale, $item]) }}" style="display:flex;gap:.35rem;align-items:center;min-width:7rem">
                                @csrf
                                @method('PATCH')
                                <input type="number" min="0" step="1" name="quantity" value="{{ old('quantity', $item->quantity) }}" required style="width:4.5rem;margin:0">
                                <button class="btn btn-secondary" type="submit" style="padding:.4rem .65rem">OK</button>
                            </form>
                        @else
                            {{ $item->quantity }}
                        @endif
                    </td>
                    <td>{{ money($item->unit_price_without_vat) }}</td>
                    <td>
                        @if ($item->discount_percent > 0) {{ number_format($item->discount_percent, 2) }}% @endif
                        @if ($item->discount_amount > 0) {{ money($item->discount_amount) }} @endif
                        @if ($item->discount_percent == 0 && $item->discount_amount == 0) — @endif
                    </td>
                    <td>{{ money($item->line_subtotal) }}</td>
                    <td>{{ money($item->line_vat) }}</td>
                    <td>{{ money($item->line_total) }}</td>
                    <td>{{ money($item->cogs_total) }}</td>
                    @if ($canEdit)
                        <td>
                            <form method="POST" action="{{ route('sales.sales.items.destroy', [$sale, $item]) }}" onsubmit="return confirm('¿Quitar este producto de la venta?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger" type="submit" style="padding:.4rem .65rem">Quitar</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($canEdit)
        <hr style="border:0;border-top:1px solid var(--line);margin:1.25rem 0">
        <h3 style="margin:0 0 .75rem;font-size:1rem">Agregar producto</h3>
        <form method="POST" action="{{ route('sales.sales.items.store', $sale) }}">
            @csrf
            <div class="grid-3" style="align-items:end">
                <div class="field">
                    <label for="add_product_id">Producto *</label>
                    <select id="add_product_id" name="product_id" required data-searchable data-add-product>
                        <option value="">— Selecciona —</option>
                        @foreach ($products as $product)
                            <option
                                value="{{ $product->id }}"
                                data-price="{{ number_format((float) $product->effectiveSalePriceWithVat(), 2, '.', '') }}"
                                @selected((string) old('product_id') === (string) $product->id)
                            >
                                {{ $product->code }} — {{ $product->name }}
                                · stock {{ (int) ($product->stock_on_hand ?? 0) }}
                                @if ($product->free_shipping) · envío gratis @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="add_quantity">Cantidad *</label>
                    <input id="add_quantity" type="number" min="1" step="1" name="quantity" value="{{ old('quantity', 1) }}" required>
                </div>
                <div class="field">
                    <label for="add_unit_price_with_vat">Precio c/IVA (USD) *</label>
                    <input
                        id="add_unit_price_with_vat"
                        type="number"
                        min="0"
                        step="0.01"
                        name="unit_price_with_vat"
                        value="{{ old('unit_price_with_vat') }}"
                        required
                        data-add-price
                    >
                </div>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="add_discount_percent">Desc. %</label>
                    <input id="add_discount_percent" type="number" min="0" max="100" step="0.01" name="discount_percent" value="{{ old('discount_percent', 0) }}">
                </div>
                <div class="field">
                    <label for="add_discount_amount">Desc. monto c/IVA</label>
                    <input id="add_discount_amount" type="number" min="0" step="0.01" name="discount_amount" value="{{ old('discount_amount', 0) }}">
                </div>
            </div>
            <div class="actions">
                <button class="btn" type="submit">Agregar a la venta</button>
            </div>
        </form>
    @endif
</div>

<div class="card">
    <h2 style="margin-top:0;font-size:1.1rem">Desglose FIFO / COGS</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Lote</th>
                <th>Cant.</th>
                <th>P. compra</th>
                <th>COGS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                @forelse ($item->lotAllocations as $allocation)
                    <tr>
                        <td>{{ $item->product?->code }}</td>
                        <td>{{ $allocation->inventoryLot?->lot_number ?? 'On demand (sin lote)' }}</td>
                        <td>{{ $allocation->quantity }}</td>
                        <td>{{ money($allocation->purchase_price) }}</td>
                        <td>{{ money($allocation->cogs_amount) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">Sin asignaciones para {{ $item->product?->code }}</td>
                    </tr>
                @endforelse
            @endforeach
        </tbody>
    </table>
</div>

@if ($canEdit)
    @include('partials.sv-geo-script')
    <script>
    (() => {
        const productSelect = document.querySelector('[data-add-product]');
        const priceInput = document.querySelector('[data-add-price]');
        const syncPrice = () => {
            if (!productSelect || !priceInput) return;
            const opt = productSelect.selectedOptions?.[0];
            if (!opt?.value) return;
            if (priceInput.dataset.manual === '1') return;
            priceInput.value = opt.dataset.price || '';
        };
        productSelect?.addEventListener('change', () => {
            if (priceInput) priceInput.dataset.manual = '';
            syncPrice();
        });
        priceInput?.addEventListener('input', () => {
            priceInput.dataset.manual = '1';
        });
        const bindTom = () => {
            const ts = productSelect?.tomselect;
            if (ts && !ts._roloPriceBound) {
                ts.on('change', () => {
                    if (priceInput) priceInput.dataset.manual = '';
                    syncPrice();
                });
                ts._roloPriceBound = true;
            }
        };
        bindTom();
        setTimeout(bindTom, 0);
        syncPrice();
    })();
    </script>
@endif

@if ($sale->canSendToSistrack())
<div id="sistrack-modal" class="sistrack-modal" hidden aria-hidden="true">
    <div class="sistrack-modal__backdrop"></div>
    <div class="sistrack-modal__panel" role="dialog" aria-modal="true" aria-labelledby="sistrack-modal-title">
        <h2 id="sistrack-modal-title" style="margin:0 0 .5rem;font-size:1.15rem">Enviando a Sistrack</h2>
        <p class="muted" id="sistrack-modal-current" style="margin:0 0 .75rem">Preparando…</p>
        <div class="sistrack-modal__progress-wrap">
            <div class="sistrack-modal__progress" id="sistrack-modal-bar" style="width:0%"></div>
        </div>
        <p id="sistrack-modal-count" style="margin:.75rem 0 0;font-weight:600">0 / 1 ventas</p>
        <div class="actions" style="margin-top:1rem;justify-content:flex-end">
            <button type="button" class="btn btn-secondary" id="sistrack-modal-close" hidden>Cerrar</button>
        </div>
    </div>
</div>
<style>
    .sistrack-modal{position:fixed;inset:0;z-index:80;display:grid;place-items:center;padding:1rem}
    .sistrack-modal[hidden]{display:none!important}
    .sistrack-modal__backdrop{position:absolute;inset:0;background:rgba(17,17,17,.45)}
    .sistrack-modal__panel{position:relative;width:min(420px,100%);background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.15rem 1.25rem}
    .sistrack-modal__progress-wrap{height:.55rem;background:#eee;border-radius:999px;overflow:hidden}
    .sistrack-modal__progress{height:100%;background:var(--accent,#e85d04);transition:width .25s ease}
</style>
<script>
(() => {
    const form = document.getElementById('sistrack-one-form');
    const btn = document.getElementById('sistrack-one-btn');
    const modal = document.getElementById('sistrack-modal');
    const modalCurrent = document.getElementById('sistrack-modal-current');
    const modalCount = document.getElementById('sistrack-modal-count');
    const modalBar = document.getElementById('sistrack-modal-bar');
    const modalClose = document.getElementById('sistrack-modal-close');
    const number = @json($sale->number);
    const csrf = form?.querySelector('input[name="_token"]')?.value || '';

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!confirm('¿Enviar esta venta a Sistrack / Express El Salvador?')) return;
        if (btn) btn.disabled = true;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        if (modalCurrent) modalCurrent.textContent = `Enviando venta ${number}`;
        if (modalCount) modalCount.textContent = '1 / 1 ventas';
        if (modalBar) modalBar.style.width = '50%';
        if (modalClose) modalClose.hidden = true;

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({}),
            });
            const data = await res.json().catch(() => ({}));
            if (modalBar) modalBar.style.width = '100%';
            if (!res.ok || data.ok === false) {
                if (modalCurrent) modalCurrent.textContent = data.message || 'Falló el envío a Sistrack.';
            } else {
                if (modalCurrent) modalCurrent.textContent = `Venta ${number} enviada correctamente.`;
            }
        } catch (err) {
            if (modalCurrent) modalCurrent.textContent = err.message || 'Error de red.';
        } finally {
            if (modalClose) modalClose.hidden = false;
            if (btn) btn.disabled = false;
        }
    });

    modalClose?.addEventListener('click', () => window.location.reload());
})();
</script>
@endif
@endsection
