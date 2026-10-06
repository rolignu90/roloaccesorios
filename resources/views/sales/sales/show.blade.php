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
    $canEdit = $sale->isConfirmed() && auth()->user()->can('sales.edit');
    $canSeeCosts = auth()->user()->can('inventory.costs');
    $canEditPerm = auth()->user()->can('sales.edit');
    $canVoidPerm = auth()->user()->can('sales.void');
    $canUpdateSistrack = $canEdit && $sale->hasSistrackLabel();
    $sistrackActions = $canEditPerm && ($canUpdateSistrack || $sale->canSyncSistrackStatus() || $sale->canResendToSistrack());
    $statusActions = $canEditPerm && ($sale->canMarkDelivered() || $sale->canMarkReturned());
    $voidAction = $canVoidPerm && ! $sale->isVoided()
        && ($sale->isConfirmed() || $sale->isInTransit() || $sale->isDelivered() || $sale->isReturned());
@endphp

<div class="topbar">
    <div>
        <a class="muted" href="{{ route('sales.sales.index') }}" style="font-size:.88rem;text-decoration:none">← Ventas</a>
        <h1>{{ $sale->number }}</h1>
        <p class="muted">
            {{ $sale->sold_at->format('d/m/Y H:i') }} ·
            @if ($customer)
                <a href="{{ route('sales.customers.show', $customer) }}">{{ $customer->name }}</a> ·
            @endif
            Vendedor: {{ $sale->seller?->name ?? '—' }} ·
            {{ config('sales.payment_methods')[$sale->payment_method] ?? $sale->payment_method }} ·
            {{ $sale->channelLabel() }}
            @if ($sale->cashSession)
                (<a href="{{ route('store.cash.show', $sale->cashSession) }}">{{ $sale->cashSession->number }}</a>)
            @endif
            @if ($sale->isPaidBeforeShipping())
                · Sistrack cobrará {{ money($sale->sistrackCollectAmount()) }} (ya pagado)
            @endif
        </p>
    </div>
    <div class="actions">
        @if ($canEditPerm && $sale->canSendToSistrack())
            <form method="POST" action="{{ route('sales.sales.send-sistrack.one', $sale) }}" id="sistrack-one-form">
                @csrf
                <button class="btn" type="submit" id="sistrack-one-btn" @if ($sale->isPaidBeforeShipping()) title="Sistrack cobrará $0.00 porque ya pagó por transferencia" @endif>Enviar a Sistrack</button>
            </form>
        @endif
        @if ($sale->isStoreSale())
            <a class="btn btn-secondary" href="{{ route('store.sales.ticket', $sale) }}" target="_blank">Imprimir ticket</a>
        @endif

        @if ($sistrackActions || $statusActions || $voidAction)
            <details class="action-menu">
                <summary class="btn btn-secondary">Más acciones ▾</summary>
                <div class="action-menu-panel">
                    @if ($sistrackActions)
                        <div class="action-menu-label">Sistrack</div>
                        @if ($canUpdateSistrack)
                            <form method="POST" action="{{ route('sales.sales.update-sistrack.one', $sale) }}" onsubmit="return confirm('¿Actualizar la orden en Sistrack con los datos actuales?\n\nMisma guía: cliente, dirección, descripción y monto a cobrar.')">
                                @csrf
                                <button type="submit" title="Envía a Sistrack los datos actuales sin crear guía nueva">Actualizar Sistrack ahora</button>
                            </form>
                        @endif
                        @if ($sale->canSyncSistrackStatus())
                            <form method="POST" action="{{ route('sales.sales.sync-sistrack-status.one', $sale) }}">
                                @csrf
                                <button type="submit">Sincronizar estado</button>
                            </form>
                        @endif
                        @if ($sale->canResendToSistrack())
                            <form method="POST" action="{{ route('sales.sales.resend-sistrack.one', $sale) }}" id="sistrack-one-form">
                                @csrf
                                <button type="submit" id="sistrack-one-btn">Reenviar (guía nueva)</button>
                            </form>
                        @endif
                    @endif

                    @if ($statusActions)
                        @if ($sistrackActions)<div class="action-menu-sep"></div>@endif
                        <div class="action-menu-label">Estado</div>
                        @if ($sale->canMarkDelivered())
                            <form method="POST" action="{{ route('sales.sales.mark-delivered.one', $sale) }}" onsubmit="return confirm('¿Marcar esta venta como entregada?')">
                                @csrf
                                <button type="submit">Marcar entregada</button>
                            </form>
                        @endif
                        @if ($sale->canMarkReturned())
                            <form method="POST" action="{{ route('sales.sales.mark-returned.one', $sale) }}" onsubmit="return confirm('¿Marcar esta venta como devolución?\n\nEl producto vuelve: se reingresa stock.\nEn costos sigue contando la pérdida de flete.')">
                                @csrf
                                <button type="submit">Marcar devolución</button>
                            </form>
                        @endif
                    @endif

                    @if ($voidAction)
                        @php
                            $voidConfirm = match (true) {
                                $sale->isConfirmed() => "¿Anular esta venta?\n\nEl pedido no cumplió el ciclo (error o cancelación). Se restaura stock.",
                                $sale->isReturned() => "¿Anular esta venta?\n\nYa es devolución: el stock ya se reingresó; solo se anula el registro.",
                                default => "¿Anular esta venta?\n\nSe restaura stock.",
                            };
                            if ($sale->hasSistrackLabel()) {
                                $voidConfirm .= "\n\nOJO: ya está en Sistrack. Esto no cancela la guía allá; cancélala también en Sistrack.";
                            }
                        @endphp
                        @if ($sistrackActions || $statusActions)<div class="action-menu-sep"></div>@endif
                        <form method="POST" action="{{ route('sales.sales.void', $sale) }}" onsubmit="return confirm(@js($voidConfirm))">
                            @csrf
                            <button class="danger" type="submit">Anular venta</button>
                        </form>
                    @endif
                </div>
            </details>
        @endif
    </div>
</div>

@if ($canUpdateSistrack)
    <label class="card" data-sistrack-toggle style="display:flex;gap:.6rem;align-items:flex-start;margin-bottom:1rem;cursor:pointer;border-color:#fcd9b6;background:#fff8f1">
        <input type="checkbox" id="update-sistrack-toggle" style="width:auto;margin-top:.2rem">
        <span>
            <strong>Actualizar también en Sistrack al guardar cambios</strong>
            <span class="muted" style="display:block;font-size:.88rem;margin-top:.2rem">
                Esta venta ya tiene guía en Sistrack (ID {{ $sale->sistrack_external_id }}). Si lo marcas, cada cambio que guardes abajo (cliente, envío, productos, descuentos) actualiza la misma orden allá: cliente, dirección, descripción y monto a cobrar.
                Si no, solo cambia aquí. También puedes usar «Más acciones → Actualizar Sistrack ahora» al terminar.
            </span>
        </span>
    </label>
    <script>
    (() => {
        const toggle = document.getElementById('update-sistrack-toggle');
        const key = 'sale-update-sistrack-{{ $sale->id }}';
        toggle.checked = sessionStorage.getItem(key) === '1';
        toggle.addEventListener('change', () => sessionStorage.setItem(key, toggle.checked ? '1' : '0'));
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!toggle.checked || !form.matches('form[data-sale-edit]')) return;
            if (form.querySelector('input[name="update_sistrack"]')) return;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'update_sistrack';
            input.value = '1';
            form.appendChild(input);
        }, true);
    })();
    </script>
@endif

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
    @if ($canSeeCosts)
    <div class="card">COGS FIFO<strong>{{ money($sale->cogs_total) }}</strong></div>
    <div class="card">Margen producto s/IVA<strong>{{ money($sale->grossMarginWithoutVat()) }}</strong>
        <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500">Sin envío</div>
    </div>
    <div class="card">Margen producto c/IVA<strong>{{ money($sale->grossMarginWithVat()) }}</strong>
        <div class="muted" style="margin-top:.35rem;font-size:.8rem;font-weight:500">Sin envío</div>
    </div>
    @endif
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="topbar" style="margin-bottom:.75rem">
        <div>
            <h2 style="margin:0;font-size:1.1rem">Vendedor</h2>
            <p class="muted" style="margin:.35rem 0 0">
                Puedes reasignar el vendedor. El número de venta ({{ $sale->number }}) no cambia.
            </p>
        </div>
    </div>

    @if (! $sale->isVoided() && ($sellers ?? collect())->isNotEmpty())
        <form method="POST" action="{{ route('sales.sales.seller.update', $sale) }}" style="display:flex;gap:.75rem;align-items:end;flex-wrap:wrap">
            @csrf
            @method('PATCH')
            <div class="field" style="flex:1;min-width:220px;margin:0">
                <label for="seller_id">Vendedor *</label>
                <select id="seller_id" name="seller_id" required data-placeholder="Buscar vendedor…">
                    @foreach ($sellers as $seller)
                        <option value="{{ $seller->id }}" @selected((int) old('seller_id', $sale->seller_id) === (int) $seller->id)>
                            {{ $seller->sale_prefix ?: 'V-' }} {{ $seller->code }} — {{ $seller->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button class="btn" type="submit">Actualizar vendedor</button>
        </form>
    @else
        <p style="margin:0"><strong>{{ $sale->seller?->name ?? '—' }}</strong>
            @if ($sale->seller)
                <span class="muted">· {{ $sale->seller->code }}</span>
            @endif
        </p>
    @endif
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
        <form method="POST" action="{{ route('sales.sales.customer.update', $sale) }}" data-sale-edit data-geo-root>
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
                @if ($canSeeCosts)
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
                @endif
            </div>
        @endif

        @if ($canEdit)
            <form method="POST" action="{{ route('sales.sales.shipping.update', $sale) }}" data-sale-edit>
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
                    Puedes corregir cantidad, precio c/IVA y descuentos de línea. El stock FIFO y la comisión COD se recalculan.
                @endif
            </p>
        </div>
    </div>

    @if ($canEdit)
        <form method="POST" action="{{ route('sales.sales.discounts.update', $sale) }}" data-sale-edit style="margin-bottom:1.25rem;padding:1rem;border:1px solid var(--line);border-radius:8px;background:var(--bg, #fafafa)">
            @csrf
            @method('PATCH')
            <h3 style="margin:0 0 .5rem;font-size:1rem">Rectificar total de productos</h3>
            <p class="muted" style="margin:0 0 .75rem">
                Escribe el <strong>total productos c/IVA</strong> que quieres cobrar (sin envío). Se ajusta el descuento global automáticamente.
                Total actual productos: {{ money((float) $sale->total - (float) $sale->shipping_amount) }} · a cobrar con envío: {{ money($sale->total) }}
            </p>
            <div class="grid-3" style="align-items:end">
                <div class="field" style="margin:0">
                    <label for="target_products_total_with_vat">Total productos c/IVA deseado</label>
                    <input
                        id="target_products_total_with_vat"
                        type="number"
                        min="0"
                        step="0.01"
                        name="target_products_total_with_vat"
                        value="{{ old('target_products_total_with_vat') }}"
                        placeholder="{{ number_format((float) $sale->total - (float) $sale->shipping_amount, 2, '.', '') }}"
                    >
                </div>
                <div class="field" style="margin:0">
                    <label for="discount_percent">Desc. global %</label>
                    <input id="discount_percent" type="number" min="0" max="100" step="0.01" name="discount_percent" value="{{ old('discount_percent', number_format((float) $sale->discount_percent, 2, '.', '')) }}">
                </div>
                <div class="field" style="margin:0">
                    <label for="discount_amount">Desc. global monto c/IVA</label>
                    <input id="discount_amount" type="number" min="0" step="0.01" name="discount_amount" value="{{ old('discount_amount', number_format((float) $sale->discount_amount, 2, '.', '')) }}">
                </div>
            </div>
            <div class="actions" style="margin-top:.75rem">
                <button class="btn" type="submit">Aplicar total / descuentos</button>
            </div>
            <p class="muted" style="margin:.5rem 0 0;font-size:.85rem">
                Si llenas el total deseado, se ignora el % y se calcula el monto de descuento. Para subir el total por encima del bruto, edita el precio unitario de la línea.
            </p>
        </form>
    @elseif ((float) $sale->discount_percent > 0 || (float) $sale->discount_amount > 0)
        <p class="muted" style="margin:0 0 1rem">
            Descuento global:
            @if ((float) $sale->discount_percent > 0) {{ number_format((float) $sale->discount_percent, 2) }}% @endif
            @if ((float) $sale->discount_amount > 0) {{ money($sale->discount_amount) }} @endif
        </p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant.</th>
                <th>P. unit c/IVA</th>
                <th>Desc. % / $</th>
                <th>Subtotal</th>
                <th>IVA</th>
                <th>Total</th>
                @if ($canSeeCosts)<th>COGS</th>@endif
                @if ($canEdit)
                    <th></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                @php
                    $itemUnitWithVat = price_with_vat((float) $item->unit_price_without_vat);
                @endphp
                <tr>
                    <td>
                        {{ $item->product?->code }} — {{ $item->product?->name }}
                        @if ($item->combo)
                            <div class="muted" style="font-size:.8rem">Combo {{ $item->combo->code }} — {{ $item->combo->name }}</div>
                        @endif
                    </td>
                    @if ($canEdit)
                        <td colspan="3">
                            <form method="POST" action="{{ route('sales.sales.items.update', [$sale, $item]) }}" data-sale-edit style="display:grid;grid-template-columns:4.5rem 5.5rem 4.25rem 4.25rem auto;gap:.35rem;align-items:center">
                                @csrf
                                @method('PATCH')
                                <input type="number" min="0" step="1" name="quantity" value="{{ old('quantity', $item->quantity) }}" required title="Cantidad" style="margin:0;width:100%">
                                <input type="number" min="0" step="0.01" name="unit_price_with_vat" value="{{ old('unit_price_with_vat', number_format($itemUnitWithVat, 2, '.', '')) }}" required title="Precio unitario c/IVA" style="margin:0;width:100%">
                                <input type="number" min="0" max="100" step="0.01" name="discount_percent" value="{{ old('discount_percent', number_format((float) $item->discount_percent, 2, '.', '')) }}" title="Desc. %" style="margin:0;width:100%">
                                <input type="number" min="0" step="0.01" name="discount_amount" value="{{ old('discount_amount', number_format((float) $item->discount_amount, 2, '.', '')) }}" title="Desc. monto c/IVA" style="margin:0;width:100%">
                                <button class="btn btn-secondary" type="submit" style="padding:.4rem .65rem">OK</button>
                            </form>
                            <div class="muted" style="font-size:.75rem;margin-top:.25rem">cant · precio c/IVA · desc% · desc$</div>
                        </td>
                    @else
                        <td>{{ $item->quantity }}</td>
                        <td>{{ money($itemUnitWithVat) }}</td>
                        <td>
                            @if ($item->discount_percent > 0) {{ number_format($item->discount_percent, 2) }}% @endif
                            @if ($item->discount_amount > 0) {{ money($item->discount_amount) }} @endif
                            @if ($item->discount_percent == 0 && $item->discount_amount == 0) — @endif
                        </td>
                    @endif
                    <td>{{ money($item->line_subtotal) }}</td>
                    <td>{{ money($item->line_vat) }}</td>
                    <td>{{ money($item->line_total) }}</td>
                    @if ($canSeeCosts)<td>{{ money($item->cogs_total) }}</td>@endif
                    @if ($canEdit)
                        <td>
                            <form method="POST" action="{{ route('sales.sales.items.destroy', [$sale, $item]) }}" data-sale-edit onsubmit="return confirm('¿Quitar este producto de la venta?')">
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
        <form method="POST" action="{{ route('sales.sales.items.store', $sale) }}" data-sale-edit>
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

@if ($canSeeCosts)
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
@endif

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

@if ($sale->canSendToSistrack() || $sale->canResendToSistrack())
<div id="sistrack-modal" class="sistrack-modal" hidden aria-hidden="true">
    <div class="sistrack-modal__backdrop"></div>
    <div class="sistrack-modal__panel" role="dialog" aria-modal="true" aria-labelledby="sistrack-modal-title">
        <h2 id="sistrack-modal-title" style="margin:0 0 .5rem;font-size:1.15rem">{{ $sale->canResendToSistrack() ? 'Reenviando a Sistrack' : 'Enviando a Sistrack' }}</h2>
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
    const isResend = @json($sale->canResendToSistrack());
    const csrf = form?.querySelector('input[name="_token"]')?.value || '';

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const ok = isResend
            ? confirm('¿Reenviar a Sistrack?\n\nSe crea una etiqueta nueva. Úsalo si borraste la anterior. Si todavía existe, Sistrack puede rechazar el duplicado.')
            : confirm('¿Enviar esta venta a Sistrack / Express El Salvador?');
        if (!ok) return;
        if (btn) btn.disabled = true;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        if (modalCurrent) modalCurrent.textContent = isResend ? `Reenviando venta ${number}` : `Enviando venta ${number}`;
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
                if (modalCurrent) modalCurrent.textContent = data.message || `Venta ${number} enviada correctamente.`;
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
