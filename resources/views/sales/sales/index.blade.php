@extends('layouts.app')

@section('title', 'Ventas')

@section('content')
<div class="topbar">
    <div>
        <h1>Ventas</h1>
        <p class="muted">Ventas con IVA, descuentos y descuento FIFO</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('sales.sales.create') }}">Nueva venta</a>
    </div>
</div>

@if ($errors->has('export') || $errors->has('sistrack') || $errors->has('status'))
    <div class="flash" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-bottom:1rem">
        {{ $errors->first('export') ?: ($errors->first('sistrack') ?: $errors->first('status')) }}
    </div>
@endif

@if (($stuckCount ?? 0) > 0 && ! request()->boolean('stuck_in_transit'))
    <div class="flash" style="background:#fff7ed;color:#9a3412;border-color:#fed7aa;margin-bottom:1rem">
        Hay <strong>{{ $stuckCount }}</strong> venta(s) en ruta hace 7+ días.
        <a href="{{ route('sales.sales.index', ['stuck_in_transit' => 1]) }}" style="margin-left:.5rem;font-weight:600">Ver atascadas</a>
    </div>
@endif

@if (($pendingSyncCount ?? 0) > 0 && ! request()->boolean('pending_sync'))
    <div class="flash" style="background:#eff6ff;color:#1e40af;border-color:#bfdbfe;margin-bottom:1rem">
        Hay <strong>{{ $pendingSyncCount }}</strong> venta(s) enviadas a Sistrack pendientes de sincronizar estado
        (Confirmada / En ruta).
        <a href="{{ route('sales.sales.index', ['pending_sync' => 1]) }}" style="margin-left:.5rem;font-weight:600">Ver pendientes</a>
    </div>
@endif

@if (request()->boolean('stuck_in_transit'))
    <div class="flash" style="background:#fff7ed;color:#9a3412;border-color:#fed7aa;margin-bottom:1rem">
        Mostrando ventas <strong>en ruta ≥ 7 días</strong>.
        Puedes marcarlas como entregadas o anularlas.
        <a href="{{ route('sales.sales.index') }}" style="margin-left:.5rem">Volver al listado</a>
    </div>
@endif

@if (request()->boolean('pending_sync'))
    <div class="flash" style="background:#eff6ff;color:#1e40af;border-color:#bfdbfe;margin-bottom:1rem">
        Mostrando ventas <strong>pendientes de sincronizar</strong> con Sistrack.
        Usa el botón “Sincronizar estados” (no hace falta seleccionar).
        <a href="{{ route('sales.sales.index') }}" style="margin-left:.5rem">Volver al listado</a>
    </div>
@endif

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('sales.sales.index') }}" style="flex-wrap:wrap">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Número, cliente o vendedor">
        <input type="date" name="from" value="{{ request('from') }}">
        <input type="date" name="to" value="{{ request('to') }}">
        <select name="seller_id">
            <option value="">Todos los vendedores</option>
            @foreach ($sellers as $seller)
                <option value="{{ $seller->id }}" @selected((string) request('seller_id') === (string) $seller->id)>
                    {{ $seller->code }} — {{ $seller->name }}
                </option>
            @endforeach
        </select>
        <select name="status">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\Sale::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="shipping">
            <option value="">Envío: todos</option>
            <option value="with" @selected(request('shipping') === 'with')>Con envío</option>
            <option value="without" @selected(request('shipping') === 'without')>Sin envío</option>
        </select>
        @if (request()->boolean('stuck_in_transit'))
            <input type="hidden" name="stuck_in_transit" value="1">
        @endif
        @if (request()->boolean('pending_sync'))
            <input type="hidden" name="pending_sync" value="1">
        @endif
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('sales.sales.export-labels') }}">
        <label for="export_date" class="muted">Exportar todo el día (etiquetas)</label>
        <input id="export_date" type="date" name="date" value="{{ request('date', now()->toDateString()) }}">
        <button class="btn btn-secondary" type="submit">Descargar CSV del día</button>
    </form>
    <p class="muted" style="margin:.5rem 0 0">
        Marca ventas para exportar CSV o enviar a Sistrack (solo pendientes de envío).
        <strong>Sincronizar estados</strong> toma automáticamente las ventas enviadas que aún no están en Entregada/Devolución.
        El listado carga más filas al hacer scroll.
    </p>
</div>

<form method="POST" action="{{ route('sales.sales.export-labels.selected') }}" id="export-selected-form">
    @csrf
    <div class="card">
        <div class="topbar" style="margin-bottom:.75rem">
            <div>
                <strong>Seleccionar para CSV / Sistrack</strong>
                <p class="muted" style="margin:.25rem 0 0" id="export-selected-count">0 seleccionadas</p>
            </div>
            <div class="actions">
                <button type="button" class="btn btn-secondary" id="select-exportable">Marcar pendientes</button>
                <button type="button" class="btn btn-secondary" id="clear-export-selection">Limpiar</button>
                <button class="btn btn-secondary" type="submit" id="export-selected-btn" disabled>Exportar CSV</button>
                <button
                    class="btn"
                    type="button"
                    id="sistrack-selected-btn"
                    disabled
                >Enviar a Sistrack</button>
                <button
                    class="btn btn-secondary"
                    type="button"
                    id="sistrack-sync-btn"
                    data-pending-url="{{ route('sales.sales.sync-sistrack-status.pending') }}"
                    data-pending-count="{{ (int) ($pendingSyncCount ?? 0) }}"
                    @if (($pendingSyncCount ?? 0) === 0) disabled @endif
                >Sincronizar estados{{ ($pendingSyncCount ?? 0) > 0 ? ' ('.$pendingSyncCount.')' : '' }}</button>
                @if (request()->boolean('stuck_in_transit'))
                    <button class="btn" type="submit" formaction="{{ route('sales.sales.mark-delivered') }}" id="mark-delivered-btn" disabled
                        onclick="return confirm('¿Marcar como entregadas las ventas seleccionadas?')">Marcar entregadas</button>
                    <button class="btn btn-danger" type="submit" formaction="{{ route('sales.sales.void-many') }}" id="void-many-btn" disabled
                        onclick="return confirm('¿Anular las ventas seleccionadas y restaurar stock?')">Anular</button>
                @endif
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:2.5rem">
                        <input type="checkbox" id="select-all-exportable" title="Marcar todas las exportables cargadas">
                    </th>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Subtotal s/IVA</th>
                    <th>IVA</th>
                    <th>Envío</th>
                    <th>Total</th>
                    <th>Margen s/IVA</th>
                    <th>Margen c/IVA</th>
                    <th>Margen real c/IVA</th>
                    <th>Estado</th>
                    <th>Sistrack</th>
                </tr>
            </thead>
            <tbody id="sales-infinite-body"
                   data-next-url="{{ $sales->hasMorePages() ? $sales->nextPageUrl().(str_contains($sales->nextPageUrl(), '?') ? '&' : '?').'infinite=1' : '' }}"
                   data-total="{{ $sales->total() }}">
                @include('sales.sales._rows', ['sales' => $sales])
            </tbody>
        </table>
        <div id="sales-infinite-status" class="muted" style="margin-top:1rem">
            Mostrando {{ $sales->count() }} de {{ $sales->total() }}
            @if ($sales->hasMorePages())
                · sigue bajando para cargar más
            @endif
        </div>
        <div id="sales-infinite-sentinel" style="height:1px"></div>
    </div>
</form>

<div id="sistrack-modal" class="sistrack-modal" hidden aria-hidden="true">
    <div class="sistrack-modal__backdrop"></div>
    <div class="sistrack-modal__panel" role="dialog" aria-modal="true" aria-labelledby="sistrack-modal-title">
        <h2 id="sistrack-modal-title" style="margin:0 0 .5rem;font-size:1.15rem">Progreso Sistrack</h2>
        <p class="muted" id="sistrack-modal-current" style="margin:0 0 .75rem">Preparando…</p>
        <div class="sistrack-modal__progress-wrap">
            <div class="sistrack-modal__progress" id="sistrack-modal-bar" style="width:0%"></div>
        </div>
        <p id="sistrack-modal-count" style="margin:.75rem 0 0;font-weight:600">0 / 0 ventas</p>
        <ul id="sistrack-modal-log" class="sistrack-modal__log"></ul>
        <div class="actions" style="margin-top:1rem;justify-content:flex-end">
            <button type="button" class="btn btn-secondary" id="sistrack-modal-close" hidden>Cerrar</button>
        </div>
    </div>
</div>

<style>
    .sistrack-modal {
        position: fixed;
        inset: 0;
        z-index: 80;
        display: grid;
        place-items: center;
        padding: 1rem;
    }
    .sistrack-modal[hidden] { display: none !important; }
    .sistrack-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(17, 17, 17, .45);
    }
    .sistrack-modal__panel {
        position: relative;
        width: min(420px, 100%);
        background: #fff;
        border: 1px solid var(--line);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 1.15rem 1.25rem;
    }
    .sistrack-modal__progress-wrap {
        height: .55rem;
        background: #eee;
        border-radius: 999px;
        overflow: hidden;
    }
    .sistrack-modal__progress {
        height: 100%;
        background: var(--accent, #e85d04);
        transition: width .25s ease;
    }
    .sistrack-modal__log {
        list-style: none;
        margin: .85rem 0 0;
        padding: 0;
        max-height: 160px;
        overflow: auto;
        font-size: .86rem;
    }
    .sistrack-modal__log li {
        padding: .35rem 0;
        border-bottom: 1px solid #f0efec;
    }
    .sistrack-modal__log li.ok { color: #166534; }
    .sistrack-modal__log li.fail { color: #991b1b; }
</style>

<script>
(() => {
    const form = document.getElementById('export-selected-form');
    const tbody = document.getElementById('sales-infinite-body');
    const statusEl = document.getElementById('sales-infinite-status');
    const sentinel = document.getElementById('sales-infinite-sentinel');
    const boxes = () => [...document.querySelectorAll('input[name="sale_ids[]"]')];
    const exportBoxes = () => [...document.querySelectorAll('[data-export-sale]')];
    const sistrackBoxes = () => [...document.querySelectorAll('[data-sistrack-sale]')];
    const stuckBoxes = () => [...document.querySelectorAll('[data-stuck-sale]')];
    const countEl = document.getElementById('export-selected-count');
    const exportBtn = document.getElementById('export-selected-btn');
    const sistrackBtn = document.getElementById('sistrack-selected-btn');
    const syncBtn = document.getElementById('sistrack-sync-btn');
    const markDeliveredBtn = document.getElementById('mark-delivered-btn');
    const voidManyBtn = document.getElementById('void-many-btn');
    const selectAll = document.getElementById('select-all-exportable');
    const csrf = form?.querySelector('input[name="_token"]')?.value || '';
    const modalTitle = document.getElementById('sistrack-modal-title');
    const modal = document.getElementById('sistrack-modal');
    const modalCurrent = document.getElementById('sistrack-modal-current');
    const modalCount = document.getElementById('sistrack-modal-count');
    const modalBar = document.getElementById('sistrack-modal-bar');
    const modalLog = document.getElementById('sistrack-modal-log');
    const modalClose = document.getElementById('sistrack-modal-close');

    let loading = false;
    let nextUrl = tbody?.dataset.nextUrl || '';
    let sending = false;

    const loadedCount = () => tbody ? tbody.querySelectorAll('tr:not([data-empty-row])').length : 0;
    const totalCount = () => Number(tbody?.dataset.total || 0);

    const syncStatus = () => {
        if (!statusEl) return;
        const loaded = loadedCount();
        const total = totalCount();
        if (total === 0) {
            statusEl.textContent = 'Sin ventas registradas.';
            return;
        }
        statusEl.textContent = nextUrl
            ? `Mostrando ${loaded} de ${total} · sigue bajando para cargar más`
            : `Mostrando ${loaded} de ${total}`;
    };

    const sync = () => {
        const selected = boxes().filter((el) => el.checked).length;
        const exportSelected = exportBoxes().filter((el) => el.checked).length;
        const sistrackSelected = sistrackBoxes().filter((el) => el.checked).length;
        const stuckSelected = stuckBoxes().filter((el) => el.checked).length;
        const total = boxes().length;
        if (countEl) countEl.textContent = selected + ' seleccionada' + (selected === 1 ? '' : 's');
        if (exportBtn) exportBtn.disabled = exportSelected === 0 || sending;
        if (sistrackBtn) sistrackBtn.disabled = sistrackSelected === 0 || sending;
        if (syncBtn) {
            const pending = Number(syncBtn.dataset.pendingCount || 0);
            syncBtn.disabled = pending === 0 || sending;
        }
        if (markDeliveredBtn) markDeliveredBtn.disabled = stuckSelected === 0 || sending;
        if (voidManyBtn) voidManyBtn.disabled = stuckSelected === 0 || sending;
        if (selectAll) {
            selectAll.checked = total > 0 && selected === total;
            selectAll.indeterminate = selected > 0 && selected < total;
        }
        syncStatus();
    };

    const openModal = (total, title) => {
        if (!modal) return;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        if (modalTitle) modalTitle.textContent = title || 'Progreso Sistrack';
        if (modalLog) modalLog.innerHTML = '';
        if (modalClose) modalClose.hidden = true;
        if (modalCurrent) modalCurrent.textContent = 'Preparando…';
        if (modalCount) modalCount.textContent = `0 / ${total} ventas`;
        if (modalBar) modalBar.style.width = '0%';
    };

    const closeModal = () => {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
    };

    const appendLog = (text, type) => {
        if (!modalLog) return;
        const li = document.createElement('li');
        li.className = type || '';
        li.textContent = text;
        modalLog.appendChild(li);
        modalLog.scrollTop = modalLog.scrollHeight;
    };

    const sendSelectedToSistrack = async () => {
        const selected = sistrackBoxes().filter((el) => el.checked);
        if (!selected.length || sending) return;
        if (!confirm(`¿Enviar ${selected.length} venta(s) a Sistrack?`)) return;

        sending = true;
        sync();
        openModal(selected.length, 'Enviando a Sistrack');

        let ok = 0;
        let fail = 0;

        for (let i = 0; i < selected.length; i++) {
            const el = selected[i];
            const number = el.dataset.saleNumber || el.value;
            const url = el.dataset.sendUrl;
            const step = i + 1;

            if (modalCurrent) modalCurrent.textContent = `Enviando venta ${number}`;
            if (modalCount) modalCount.textContent = `${step} / ${selected.length} ventas`;
            if (modalBar) modalBar.style.width = `${Math.round((step / selected.length) * 100)}%`;

            try {
                const res = await fetch(url, {
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
                if (!res.ok || data.ok === false) {
                    fail += 1;
                    appendLog(`${number}: ${data.message || 'Error al enviar'}`, 'fail');
                } else {
                    ok += 1;
                    appendLog(`${number}: enviada`, 'ok');
                    const row = el.closest('tr');
                    el.replaceWith(Object.assign(document.createElement('span'), {
                        className: 'muted',
                        title: 'Ya enviada a Sistrack',
                        textContent: '—',
                    }));
                    const badgeCell = row?.querySelector('td:last-child');
                    if (badgeCell) {
                        badgeCell.innerHTML = '<span class="badge badge-ok">Enviado a Sistrack</span>';
                    }
                }
            } catch (err) {
                fail += 1;
                appendLog(`${number}: ${err.message || 'Error de red'}`, 'fail');
            }
        }

        if (modalCurrent) {
            modalCurrent.textContent = fail
                ? `Listo: ${ok} enviada(s), ${fail} fallida(s).`
                : `Listo: ${ok} venta(s) enviada(s).`;
        }
        if (modalClose) modalClose.hidden = false;
        sending = false;
        sync();
    };

    const syncSelectedStatuses = async () => {
        if (sending) return;

        const pendingUrl = syncBtn?.dataset.pendingUrl;
        if (!pendingUrl) {
            alert('No está configurada la sincronización.');
            return;
        }

        sending = true;
        sync();
        openModal(1, 'Sincronizando estados');
        if (modalCurrent) modalCurrent.textContent = 'Buscando ventas pendientes…';

        let queue = [];
        try {
            const res = await fetch(pendingUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.message || 'No se pudo obtener el listado pendiente');
            }
            queue = Array.isArray(data.sales) ? data.sales : [];
            if (syncBtn) {
                syncBtn.dataset.pendingCount = String(data.count ?? queue.length);
                syncBtn.textContent = (data.count ?? queue.length) > 0
                    ? `Sincronizar estados (${data.count ?? queue.length})`
                    : 'Sincronizar estados';
            }
        } catch (err) {
            sending = false;
            sync();
            closeModal();
            alert(err.message || 'Error al buscar pendientes');
            return;
        }

        if (!queue.length) {
            sending = false;
            sync();
            if (modalCurrent) modalCurrent.textContent = 'No hay ventas pendientes de sincronizar.';
            if (modalClose) modalClose.hidden = false;
            return;
        }

        if (!confirm(`¿Sincronizar ${queue.length} venta(s) pendientes desde Sistrack?`)) {
            sending = false;
            sync();
            closeModal();
            return;
        }

        openModal(queue.length, 'Sincronizando estados');

        let ok = 0;
        let changed = 0;
        let fail = 0;

        for (let i = 0; i < queue.length; i++) {
            const item = queue[i];
            const number = item.number || item.id;
            const url = item.sync_url;
            const step = i + 1;

            if (!url) {
                fail += 1;
                appendLog(`${number}: falta URL de sincronización`, 'fail');
                continue;
            }

            if (modalCurrent) modalCurrent.textContent = `Sincronizando venta ${number}`;
            if (modalCount) modalCount.textContent = `${step} / ${queue.length} ventas`;
            if (modalBar) modalBar.style.width = `${Math.round((step / queue.length) * 100)}%`;

            try {
                const res = await fetch(url, {
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
                if (!res.ok || data.ok === false) {
                    fail += 1;
                    appendLog(`${number}: ${data.message || 'Error al sincronizar'}`, 'fail');
                } else {
                    ok += 1;
                    if (data.changed) changed += 1;
                    appendLog(`${number}: ${data.message || 'OK'}`, 'ok');
                }
            } catch (err) {
                fail += 1;
                appendLog(`${number}: ${err.message || 'Error de red'}`, 'fail');
            }
        }

        if (modalCurrent) {
            modalCurrent.textContent = fail
                ? `Listo: ${ok} ok (${changed} cambiaron), ${fail} fallida(s).`
                : `Listo: ${ok} sincronizada(s), ${changed} con cambio.`;
        }
        if (modalClose) modalClose.hidden = false;
        if (syncBtn) {
            // Tras sync, lo pendiente baja; el reload al cerrar refresca el contador exacto.
            const remaining = Math.max(0, queue.length - changed);
            syncBtn.dataset.pendingCount = String(remaining);
        }
        sending = false;
        sync();
    };

    const loadMore = async () => {
        if (loading || !nextUrl || !tbody) return;
        loading = true;
        if (statusEl) statusEl.textContent = 'Cargando más ventas…';

        try {
            const res = await fetch(nextUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            if (!res.ok) throw new Error('No se pudo cargar más ventas');
            const data = await res.json();
            tbody.querySelector('[data-empty-row]')?.remove();
            if (data.html) tbody.insertAdjacentHTML('beforeend', data.html);
            nextUrl = data.next_page_url || '';
            if (tbody) tbody.dataset.nextUrl = nextUrl;
            if (data.total != null && tbody) tbody.dataset.total = String(data.total);
            sync();
        } catch (err) {
            if (statusEl) statusEl.textContent = 'Error al cargar. Intenta de nuevo bajando el scroll.';
            console.error(err);
        } finally {
            loading = false;
        }
    };

    selectAll?.addEventListener('change', () => {
        boxes().forEach((el) => { el.checked = selectAll.checked; });
        sync();
    });
    document.getElementById('select-exportable')?.addEventListener('click', () => {
        boxes().forEach((el) => { el.checked = false; });
        exportBoxes().forEach((el) => { el.checked = true; });
        sync();
    });
    document.getElementById('clear-export-selection')?.addEventListener('click', () => {
        boxes().forEach((el) => { el.checked = false; });
        sync();
    });
    form?.addEventListener('change', (e) => {
        if (e.target.matches('input[name="sale_ids[]"]')) sync();
    });
    form?.addEventListener('submit', (e) => {
        if (exportBoxes().filter((el) => el.checked).length === 0) {
            e.preventDefault();
            alert('Selecciona al menos una venta exportable.');
        }
    });
    sistrackBtn?.addEventListener('click', sendSelectedToSistrack);
    syncBtn?.addEventListener('click', syncSelectedStatuses);
    modalClose?.addEventListener('click', () => {
        closeModal();
        // Recargar para sincronizar badges/filtros tras envíos parciales.
        window.location.reload();
    });

    if (sentinel && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) loadMore();
        }, { rootMargin: '240px 0px' });
        observer.observe(sentinel);
    }

    sync();
})();
</script>
@endsection
