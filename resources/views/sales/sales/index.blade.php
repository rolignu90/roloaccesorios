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

@if ($errors->has('export'))
    <div class="flash" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-bottom:1rem">
        {{ $errors->first('export') }}
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
            <option value="confirmed" @selected(request('status') === 'confirmed')>Confirmada</option>
            <option value="voided" @selected(request('status') === 'voided')>Anulada</option>
        </select>
        <select name="shipping">
            <option value="">Envío: todos</option>
            <option value="with" @selected(request('shipping') === 'with')>Con envío</option>
            <option value="without" @selected(request('shipping') === 'without')>Sin envío</option>
        </select>
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
        También puedes marcar ventas abajo y exportar solo la selección. Solo ventas <strong>confirmadas con envío</strong>.
        El listado carga más filas al hacer scroll.
    </p>
</div>

<form method="POST" action="{{ route('sales.sales.export-labels.selected') }}" id="export-selected-form">
    @csrf
    <div class="card">
        <div class="topbar" style="margin-bottom:.75rem">
            <div>
                <strong>Seleccionar para CSV</strong>
                <p class="muted" style="margin:.25rem 0 0" id="export-selected-count">0 seleccionadas</p>
            </div>
            <div class="actions">
                <button type="button" class="btn btn-secondary" id="select-exportable">Marcar exportables</button>
                <button type="button" class="btn btn-secondary" id="clear-export-selection">Limpiar</button>
                <button class="btn" type="submit" id="export-selected-btn" disabled>Exportar seleccionadas</button>
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

<script>
(() => {
    const form = document.getElementById('export-selected-form');
    const tbody = document.getElementById('sales-infinite-body');
    const statusEl = document.getElementById('sales-infinite-status');
    const sentinel = document.getElementById('sales-infinite-sentinel');
    const boxes = () => [...document.querySelectorAll('[data-export-sale]')];
    const countEl = document.getElementById('export-selected-count');
    const exportBtn = document.getElementById('export-selected-btn');
    const selectAll = document.getElementById('select-all-exportable');

    let loading = false;
    let nextUrl = tbody?.dataset.nextUrl || '';

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
        const total = boxes().length;
        if (countEl) countEl.textContent = selected + ' seleccionada' + (selected === 1 ? '' : 's');
        if (exportBtn) exportBtn.disabled = selected === 0;
        if (selectAll) {
            selectAll.checked = total > 0 && selected === total;
            selectAll.indeterminate = selected > 0 && selected < total;
        }
        syncStatus();
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
        boxes().forEach((el) => { el.checked = true; });
        sync();
    });
    document.getElementById('clear-export-selection')?.addEventListener('click', () => {
        boxes().forEach((el) => { el.checked = false; });
        sync();
    });
    form?.addEventListener('change', (e) => {
        if (e.target.matches('[data-export-sale]')) sync();
    });
    form?.addEventListener('submit', (e) => {
        if (boxes().filter((el) => el.checked).length === 0) {
            e.preventDefault();
            alert('Selecciona al menos una venta con envío.');
        }
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
