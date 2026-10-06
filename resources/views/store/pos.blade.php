@extends('layouts.app')

@section('title', 'Punto de venta')

@section('content')
<style>
    .pos { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(320px, 1fr); gap: 1rem; align-items: start; }
    .pos-search { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .75rem; }
    .pos-search #pos-scan-status { flex-basis: 100%; }
    .pos-search input { flex: 1; font-size: 1.1rem; padding: .8rem 1rem; }
    .pos-kind { display: flex; gap: .4rem; margin-bottom: .75rem; }
    .pos-kind button { flex: 1; }
    .pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .6rem; max-height: 62vh; overflow: auto; padding: .15rem; }
    .pos-tile {
        text-align: left; border: 1px solid var(--line); background: #fff; border-radius: var(--radius);
        padding: .7rem .75rem; cursor: pointer; font: inherit; color: var(--ink); display: flex; flex-direction: column; gap: .3rem;
        transition: border-color .15s ease, transform .15s ease;
    }
    .pos-tile:hover { border-color: var(--signal); transform: translateY(-1px); }
    .pos-tile[disabled] { opacity: .45; cursor: not-allowed; transform: none; }
    .pos-tile-name { font-weight: 600; font-size: .92rem; line-height: 1.2; }
    .pos-tile-meta { display: flex; justify-content: space-between; font-size: .8rem; color: var(--muted); }
    .pos-tile-price { font-weight: 700; color: var(--ink); font-size: 1rem; }
    .pos-cart { position: sticky; top: 1rem; }
    .pos-lines { max-height: 34vh; overflow: auto; margin: 0 0 .75rem; }
    .pos-line { display: grid; grid-template-columns: 1fr auto; gap: .35rem .75rem; padding: .6rem 0; border-bottom: 1px dashed var(--line); }
    .pos-line-name { font-weight: 600; font-size: .92rem; }
    .pos-line-controls { display: flex; align-items: center; gap: .35rem; }
    .pos-line-controls button { width: 2rem; height: 2rem; border-radius: .4rem; border: 1px solid var(--line); background: #fff; cursor: pointer; font-weight: 700; }
    .pos-line-controls input { width: 3.2rem; text-align: center; padding: .3rem; }
    .pos-line-price { width: 5.5rem; padding: .3rem .4rem; text-align: right; }
    .pos-line-total { font-weight: 700; text-align: right; }
    .pos-remove { background: none; border: 0; color: var(--danger); cursor: pointer; font-size: .8rem; padding: 0; }
    .pos-totals { display: grid; gap: .3rem; margin: .5rem 0 1rem; }
    .pos-totals div { display: flex; justify-content: space-between; }
    .pos-total { font-size: 1.6rem; font-weight: 700; font-family: var(--font-display); letter-spacing: .02em; }
    .pos-methods { display: grid; grid-template-columns: repeat(3, 1fr); gap: .4rem; margin-bottom: .75rem; }
    .pos-methods button { padding: .75rem .5rem; border: 1px solid var(--line); background: #fff; border-radius: var(--radius); cursor: pointer; font: inherit; font-weight: 600; }
    .pos-methods button.active { background: var(--ink); color: #fff; border-color: var(--ink); }
    .pos-quick { display: flex; flex-wrap: wrap; gap: .35rem; margin: .4rem 0 .6rem; }
    .pos-quick button { padding: .4rem .65rem; border: 1px solid var(--line); background: #fff; border-radius: .4rem; cursor: pointer; font: inherit; }
    .pos-change { font-size: 1.25rem; font-weight: 700; }
    .pos-checkout { width: 100%; font-size: 1.15rem; padding: 1rem; }
    .pos-empty { padding: 2rem 0; text-align: center; color: var(--muted); }
    .pos-session { display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; font-size: .9rem; }
    @media (max-width: 1100px) { .pos { grid-template-columns: 1fr; } .pos-cart { position: static; } }
</style>

<div class="topbar">
    <div>
        <h1>Punto de venta · {{ $currentStore->name }}</h1>
        @if ($session)
            <p class="muted pos-session">
                <span><span class="badge badge-ok">Caja abierta</span> {{ $session->number }}</span>
                <span>Atiende: <strong>{{ auth()->user()->name }}</strong></span>
                <span>Responsable de caja: {{ $session->cashier?->name ?? 'sin asignar' }}</span>
                <span>Desde {{ $session->opened_at->format('d/m H:i') }}</span>
                <span>{{ $summary['sales_count'] }} ventas · {{ money($summary['sales_total']) }}</span>
                <span>Efectivo esperado: <strong>{{ money($summary['expected_cash']) }}</strong></span>
            </p>
        @else
            <p class="muted">Abre la caja para empezar a cobrar.</p>
        @endif
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('store.drawer') }}" id="pos-drawer-open">Abrir gaveta (F9)</a>
        @if ($session)
            <a class="btn btn-secondary" href="{{ route('store.cash.show', $session) }}">Caja / corte</a>
        @endif
        @if ($hasManyStores)
            <a class="btn btn-secondary" href="{{ route('store.select') }}">Cambiar tienda</a>
        @endif
        @if ($canSwitchUser)
            <button class="btn btn-secondary" type="button" id="pos-switch-open">Cambiar cajero (F8)</button>
        @endif
        <a class="btn btn-secondary" href="{{ route('sales.sales.index', ['channel' => 'store']) }}">Ventas de tienda</a>
    </div>
</div>
<script>
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') { e.preventDefault(); window.location.href = document.getElementById('pos-drawer-open').href; }
    });
</script>

@if ($canSwitchUser)
    <dialog id="pos-switch" style="border:1px solid var(--line);border-radius:var(--radius);padding:1.25rem;max-width:320px;width:100%">
        <form method="POST" action="{{ route('store.switch') }}">
            @csrf
            <h2 style="margin-top:0;font-size:1.1rem">Cambiar cajero</h2>
            <p class="muted" style="margin-top:0">Ahora: {{ auth()->user()->name }}. Ingresa tu PIN de caja.</p>
            <input type="password" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off" required
                   style="width:100%;font-size:1.6rem;letter-spacing:.4em;text-align:center;padding:.6rem">
            <div class="actions" style="margin-top:1rem">
                <button class="btn" type="submit">Entrar</button>
                <button class="btn btn-secondary" type="button" onclick="this.closest('dialog').close()">Cancelar</button>
            </div>
        </form>
    </dialog>
    <script>
    (() => {
        const dialog = document.getElementById('pos-switch');
        const open = () => { dialog.showModal(); dialog.querySelector('input[name=pin]').focus(); };
        document.getElementById('pos-switch-open')?.addEventListener('click', open);
        document.addEventListener('keydown', (e) => { if (e.key === 'F8') { e.preventDefault(); open(); } });
    })();
    </script>
@endif

@if (! $session)
    <div class="card" style="max-width:520px">
        <h2 style="margin-top:0;font-size:1.1rem">Abrir caja de {{ $currentStore->name }}</h2>
        <form method="POST" action="{{ route('store.cash.open') }}">
            @csrf
            <input type="hidden" name="store_id" value="{{ $currentStore->id }}">
            <input type="hidden" name="redirect_to_pos" value="1">
            <div class="field">
                <label for="opening_amount">Fondo inicial en efectivo (USD) *</label>
                <input id="opening_amount" type="number" step="0.01" min="0" name="opening_amount" value="{{ old('opening_amount', '0.00') }}" required autofocus>
            </div>
            <div class="field">
                <label for="cashier_id">Cajero (vendedor)</label>
                <select id="cashier_id" name="cashier_id">
                    <option value="">— Sin cajero —</option>
                    @foreach ($sellers as $seller)
                        <option value="{{ $seller->id }}" @selected((string) old('cashier_id', auth()->user()->seller_id) === (string) $seller->id)>{{ $seller->name }}</option>
                    @endforeach
                </select>
                <small class="muted">Las ventas de esta caja saldrán a nombre del cajero por defecto.</small>
            </div>
            <button class="btn" type="submit">Abrir caja</button>
        </form>
    </div>
@else
    <form method="POST" action="{{ route('store.pos.checkout') }}" id="pos-form">
        @csrf
        <div class="pos">
            <div class="card">
                <div class="pos-search">
                    <input type="search" id="pos-search" placeholder="Escanea o escribe código / nombre y presiona Enter" autocomplete="off" autofocus>
                    <div id="pos-scan-status" role="alert" hidden style="margin-top:.4rem;padding:.4rem .6rem;border-radius:8px;background:#fee2e2;color:#991b1b;font-weight:600;font-size:.9rem"></div>
                </div>
                <div class="pos-kind">
                    <button type="button" class="btn" data-kind="products">Productos</button>
                    <button type="button" class="btn btn-secondary" data-kind="combos">Combos</button>
                </div>
                <div class="pos-grid" id="pos-grid"></div>
            </div>

            <div class="card pos-cart">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                    <strong>Carrito</strong>
                    <button type="button" class="btn-link" id="pos-clear">Vaciar</button>
                </div>
                <div class="pos-lines" id="pos-lines"></div>

                <div class="grid-2" style="gap:.6rem">
                    <div class="field" style="margin:0">
                        <label for="discount_amount">Descuento (USD)</label>
                        <input id="discount_amount" type="number" step="0.01" min="0" name="discount_amount" value="{{ old('discount_amount', '0') }}" @unless ($canDiscount) readonly title="Sin permiso para descuentos" @endunless>
                    </div>
                    <div class="field" style="margin:0">
                        <label for="seller_id">Vendedor</label>
                        <select id="seller_id" name="seller_id" data-no-search>
                            @foreach ($sellers as $seller)
                                <option value="{{ $seller->id }}" @selected((int) old('seller_id', $defaultSellerId) === (int) $seller->id)>{{ $seller->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="field" style="margin:.6rem 0 0">
                    <label for="customer_id">Cliente (opcional)</label>
                    <select id="customer_id" name="customer_id" data-placeholder="Consumidor final">
                        <option value="">Consumidor final</option>
                        @foreach ($customers as $customer)
                            @continue($customer->id === $walkInCustomerId)
                            <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>
                                {{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="pos-totals">
                    <div><span class="muted">Subtotal</span><span id="pos-subtotal">$0.00</span></div>
                    <div><span class="muted">Descuento</span><span id="pos-discount">−$0.00</span></div>
                    <div><span>Total (IVA incluido)</span><span class="pos-total" id="pos-total">$0.00</span></div>
                </div>

                <input type="hidden" name="payment_method" id="payment_method" value="{{ old('payment_method', 'cash') }}">
                <div class="pos-methods">
                    @foreach ($paymentMethods as $key => $label)
                        <button type="button" data-method="{{ $key }}">{{ $label }}</button>
                    @endforeach
                </div>

                <div id="cash-box">
                    <div class="field" style="margin:0">
                        <label for="amount_received">Efectivo recibido</label>
                        <input id="amount_received" type="number" step="0.01" min="0" name="amount_received" value="{{ old('amount_received') }}" style="font-size:1.15rem">
                    </div>
                    <div class="pos-quick" id="pos-quick"></div>
                    <p style="margin:0 0 .75rem">Cambio: <span class="pos-change" id="pos-change">$0.00</span></p>
                </div>

                <div class="field">
                    <label for="notes">Nota</label>
                    <input id="notes" type="text" name="notes" value="{{ old('notes') }}" maxlength="1000" autocomplete="off">
                </div>

                <div id="pos-hidden"></div>
                <button class="btn pos-checkout" type="submit" id="pos-submit" disabled>Cobrar (F4)</button>
            </div>
        </div>
    </form>

    <script type="application/json" id="pos-products">@json($products)</script>
    <script type="application/json" id="pos-combos">@json($combos)</script>
    <script>
    (() => {
        const products = JSON.parse(document.getElementById('pos-products').textContent || '[]');
        const combos = JSON.parse(document.getElementById('pos-combos').textContent || '[]');
        const storageKey = 'rolo-pos-cart-{{ $currentStore->id }}';
        const canOverridePrice = @json($canOverridePrice);
        const fmt = (n) => '$' + (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
        const norm = (s) => String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

        const grid = document.getElementById('pos-grid');
        const search = document.getElementById('pos-search');
        const linesEl = document.getElementById('pos-lines');
        const hidden = document.getElementById('pos-hidden');
        const submit = document.getElementById('pos-submit');
        const discountInput = document.getElementById('discount_amount');
        const receivedInput = document.getElementById('amount_received');
        const methodInput = document.getElementById('payment_method');
        const cashBox = document.getElementById('cash-box');

        let kind = 'products';
        let cart = [];
        try { cart = JSON.parse(sessionStorage.getItem(storageKey) || '[]'); } catch { cart = []; }

        const catalog = () => (kind === 'products' ? products : combos);
        const canSell = (item) => item.on_demand || item.stock > 0;

        const renderGrid = () => {
            const q = norm(search.value.trim());
            const list = catalog()
                .filter((item) => !q || norm(item.code).includes(q) || norm(item.name).includes(q))
                .slice(0, 120);
            if (!list.length) {
                grid.innerHTML = '<div class="pos-empty">Sin resultados</div>';
                return;
            }
            grid.innerHTML = '';
            list.forEach((item) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pos-tile';
                btn.disabled = !canSell(item);
                btn.innerHTML = `
                    <span class="pos-tile-name"></span>
                    <span class="pos-tile-meta"><span class="code"></span><span class="stock"></span></span>
                    <span class="pos-tile-price">${fmt(item.price)}</span>`;
                btn.querySelector('.pos-tile-name').textContent = (item.favorite ? '★ ' : '') + item.name;
                btn.querySelector('.code').textContent = item.code;
                btn.querySelector('.stock').textContent = item.on_demand ? 'On demand' : `Stock ${item.stock}`;
                btn.addEventListener('click', () => addToCart(kind, item));
                grid.appendChild(btn);
            });
        };

        const addToCart = (type, item) => {
            if (!canSell(item)) return;
            const existing = cart.find((line) => line.type === type && line.id === item.id);
            if (existing) {
                if (!item.on_demand && existing.qty >= item.stock) {
                    alert(`Solo hay ${item.stock} en stock de ${item.name}.`);
                    return;
                }
                existing.qty += 1;
            } else {
                cart.push({ type, id: item.id, name: item.name, code: item.code, price: Number(item.price), qty: 1, stock: item.stock, on_demand: !!item.on_demand });
            }
            render();
        };

        const subtotal = () => cart.reduce((sum, line) => sum + line.qty * line.price, 0);
        const discount = () => Math.min(subtotal(), Math.max(0, parseFloat(discountInput.value || '0') || 0));
        const total = () => Math.max(0, Math.round((subtotal() - discount()) * 100) / 100);

        const renderLines = () => {
            if (!cart.length) {
                linesEl.innerHTML = '<div class="pos-empty">Agrega productos para cobrar</div>';
                return;
            }
            linesEl.innerHTML = '';
            cart.forEach((line, index) => {
                const row = document.createElement('div');
                row.className = 'pos-line';
                row.innerHTML = `
                    <div>
                        <div class="pos-line-name"></div>
                        <div class="muted" style="font-size:.78rem"></div>
                    </div>
                    <div class="pos-line-total"></div>
                    <div class="pos-line-controls">
                        <button type="button" data-act="dec">−</button>
                        <input type="number" min="1" data-act="qty">
                        <button type="button" data-act="inc">+</button>
                        ${line.type === 'products' && canOverridePrice ? '<input type="number" step="0.01" min="0" class="pos-line-price" data-act="price" title="Precio c/IVA">' : ''}
                    </div>
                    <div style="text-align:right"><button type="button" class="pos-remove" data-act="remove">Quitar</button></div>`;
                row.querySelector('.pos-line-name').textContent = (line.type === 'combos' ? 'Combo · ' : '') + line.name;
                row.querySelector('.muted').textContent = `${line.code} · ${fmt(line.price)} c/u`;
                row.querySelector('.pos-line-total').textContent = fmt(line.qty * line.price);
                row.querySelector('[data-act="qty"]').value = line.qty;
                const priceInput = row.querySelector('[data-act="price"]');
                if (priceInput) priceInput.value = line.price.toFixed(2);

                row.addEventListener('click', (e) => {
                    const act = e.target.dataset.act;
                    if (act === 'inc') {
                        if (!line.on_demand && line.qty >= line.stock) { alert(`Solo hay ${line.stock} en stock.`); return; }
                        line.qty += 1; render();
                    }
                    if (act === 'dec') { line.qty = Math.max(1, line.qty - 1); render(); }
                    if (act === 'remove') { cart.splice(index, 1); render(); }
                });
                row.addEventListener('change', (e) => {
                    const act = e.target.dataset.act;
                    if (act === 'qty') {
                        let qty = Math.max(1, parseInt(e.target.value || '1', 10) || 1);
                        if (!line.on_demand && qty > line.stock) { alert(`Solo hay ${line.stock} en stock.`); qty = line.stock; }
                        line.qty = qty; render();
                    }
                    if (act === 'price') { line.price = Math.max(0, parseFloat(e.target.value || '0') || 0); render(); }
                });
                linesEl.appendChild(row);
            });
        };

        const renderQuick = () => {
            const quick = document.getElementById('pos-quick');
            const t = total();
            const options = [t, ...[1, 5, 10, 20, 50, 100].filter((v) => v > t).slice(0, 4)];
            const rounded = Math.ceil(t);
            if (rounded > t && !options.includes(rounded)) options.splice(1, 0, rounded);
            quick.innerHTML = '';
            [...new Set(options.map((v) => Math.round(v * 100) / 100))].forEach((value) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.textContent = value === t ? `Exacto ${fmt(value)}` : fmt(value);
                b.addEventListener('click', () => { receivedInput.value = value.toFixed(2); renderTotals(); });
                quick.appendChild(b);
            });
        };

        const renderTotals = () => {
            document.getElementById('pos-subtotal').textContent = fmt(subtotal());
            document.getElementById('pos-discount').textContent = '−' + fmt(discount());
            document.getElementById('pos-total').textContent = fmt(total());

            const isCash = methodInput.value === 'cash';
            cashBox.style.display = isCash ? '' : 'none';
            const received = parseFloat(receivedInput.value || '0') || 0;
            const change = received - total();
            document.getElementById('pos-change').textContent = received > 0 ? fmt(Math.max(0, change)) : '$0.00';

            const cashOk = !isCash || !receivedInput.value || received + 0.009 >= total();
            submit.disabled = !cart.length || !cashOk;
            submit.textContent = cart.length ? `Cobrar ${fmt(total())} (F4)` : 'Cobrar (F4)';
        };

        const syncHidden = () => {
            hidden.innerHTML = '';
            let i = 0, c = 0;
            cart.forEach((line) => {
                if (line.type === 'products') {
                    hidden.insertAdjacentHTML('beforeend',
                        `<input type="hidden" name="items[${i}][product_id]" value="${line.id}">` +
                        `<input type="hidden" name="items[${i}][quantity]" value="${line.qty}">` +
                        `<input type="hidden" name="items[${i}][unit_price_with_vat]" value="${line.price.toFixed(2)}">`);
                    i++;
                } else {
                    hidden.insertAdjacentHTML('beforeend',
                        `<input type="hidden" name="combos[${c}][combo_id]" value="${line.id}">` +
                        `<input type="hidden" name="combos[${c}][quantity]" value="${line.qty}">`);
                    c++;
                }
            });
        };

        const render = () => {
            sessionStorage.setItem(storageKey, JSON.stringify(cart));
            renderLines();
            renderQuick();
            renderTotals();
        };

        const setMethod = (method) => {
            methodInput.value = method;
            document.querySelectorAll('[data-method]').forEach((b) => b.classList.toggle('active', b.dataset.method === method));
            renderTotals();
        };

        document.querySelectorAll('[data-method]').forEach((b) => b.addEventListener('click', () => setMethod(b.dataset.method)));
        document.querySelectorAll('[data-kind]').forEach((b) => b.addEventListener('click', () => {
            kind = b.dataset.kind;
            document.querySelectorAll('[data-kind]').forEach((x) => x.classList.toggle('btn-secondary', x.dataset.kind !== kind));
            renderGrid();
            search.focus();
        }));

        const scanStatus = document.getElementById('pos-scan-status');
        let audio;
        const beep = () => {
            try {
                audio = audio || new (window.AudioContext || window.webkitAudioContext)();
                const osc = audio.createOscillator();
                const gain = audio.createGain();
                osc.type = 'square';
                osc.frequency.value = 220;
                gain.gain.value = 0.08;
                osc.connect(gain).connect(audio.destination);
                osc.start();
                osc.stop(audio.currentTime + 0.25);
            } catch (_) {}
        };
        const flashStatus = (text) => {
            scanStatus.textContent = text;
            scanStatus.hidden = !text;
            clearTimeout(flashStatus.t);
            if (text) flashStatus.t = setTimeout(() => { scanStatus.hidden = true; }, 4000);
        };

        const findByCode = (raw) => {
            const q = norm(raw.trim());
            return products.find((p) => norm(p.code) === q) || combos.find((p) => norm(p.code) === q);
        };
        const addExact = (item) => addToCart(products.includes(item) ? 'products' : 'combos', item);

        search.addEventListener('input', renderGrid);
        search.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const q = norm(search.value.trim());
            if (!q) return;
            const exact = findByCode(search.value);
            if (exact) {
                addExact(exact);
            } else {
                const matches = catalog().filter((item) => canSell(item) && (norm(item.code).includes(q) || norm(item.name).includes(q)));
                if (matches.length === 1) {
                    addToCart(kind, matches[0]);
                } else {
                    if (!matches.length) { beep(); flashStatus(`No se encontró «${search.value.trim()}»`); }
                    return;
                }
            }
            flashStatus('');
            search.value = '';
            renderGrid();
        });

        // Barcode scanners type like a very fast keyboard ending in Enter. Catch scans
        // even when the focus is outside the search box (a button, the cash received field…).
        const scan = { buffer: '', last: 0, fast: true, field: null, fieldValue: '' };
        const isEditable = (el) => el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
        document.addEventListener('keydown', (e) => {
            const target = e.target;
            if (target === search || e.ctrlKey || e.metaKey || e.altKey) return;
            if (document.querySelector('dialog[open]')) return;

            const now = performance.now();
            if (e.key.length === 1) {
                if (now - scan.last > 80) {
                    scan.buffer = '';
                    scan.fast = true;
                    scan.field = isEditable(target) ? target : null;
                    scan.fieldValue = scan.field ? scan.field.value : '';
                } else if (scan.buffer && now - scan.last > 50) {
                    scan.fast = false;
                }
                scan.buffer += e.key;
                scan.last = now;
                return;
            }

            if (e.key !== 'Enter') return;
            const code = scan.buffer;
            const wasScan = code.length >= 3 && scan.fast && now - scan.last < 80;
            scan.buffer = '';
            if (!wasScan) return;

            e.preventDefault();
            if (scan.field && 'value' in scan.field) {
                scan.field.value = scan.fieldValue;
                scan.field.dispatchEvent(new Event('input', { bubbles: true }));
            }
            const item = findByCode(code);
            if (item) {
                addExact(item);
                flashStatus('');
            } else {
                beep();
                flashStatus(`No se encontró «${code.trim()}»`);
            }
            search.focus();
        }, true);

        discountInput.addEventListener('input', () => { renderQuick(); renderTotals(); });
        receivedInput.addEventListener('input', renderTotals);
        document.getElementById('pos-clear').addEventListener('click', () => {
            if (cart.length && !confirm('¿Vaciar el carrito?')) return;
            cart = []; render(); search.focus();
        });

        document.getElementById('pos-form').addEventListener('submit', (e) => {
            if (!cart.length) { e.preventDefault(); return; }
            syncHidden();
            submit.disabled = true;
            submit.textContent = 'Procesando…';
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'F4') { e.preventDefault(); if (!submit.disabled) document.getElementById('pos-form').requestSubmit(); }
            if (e.key === 'F2') { e.preventDefault(); search.focus(); }
        });

        setMethod(methodInput.value || 'cash');
        renderGrid();
        render();
    })();
    </script>
@endif
@endsection
