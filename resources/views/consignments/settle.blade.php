@extends('layouts.app')

@section('title', 'Liquidar consignatario')

@section('content')
<div class="topbar">
    <div>
        <h1>Liquidar consignatario</h1>
        <p class="muted">Varias entregas en un solo pago (vendedor o cliente)</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('consignments.dashboard') }}">Dashboard</a>
        <a class="btn btn-secondary" href="{{ route('consignments.index') }}">Listado</a>
    </div>
</div>

@if ($errors->has('settle') || $errors->has('allocations'))
    <div class="flash" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-bottom:1rem">
        {{ $errors->first('settle') ?: $errors->first('allocations') }}
    </div>
@endif

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('consignments.settle') }}" id="party-picker" style="flex-wrap:wrap;align-items:end">
        <div class="field" style="margin:0">
            <label for="party_type">Tipo *</label>
            <select id="party_type" name="party_type" required>
                <option value="">—</option>
                <option value="seller" @selected($partyType === 'seller')>Vendedor</option>
                <option value="customer" @selected($partyType === 'customer')>Cliente</option>
            </select>
        </div>
        <div class="field" style="margin:0;{{ $partyType === 'customer' ? 'display:none' : '' }}" id="seller-field">
            <label for="seller_id">Vendedor</label>
            <select id="seller_id" name="seller_id">
                <option value="">—</option>
                @foreach ($sellers as $seller)
                    <option value="{{ $seller->id }}" @selected((int) $sellerId === (int) $seller->id)>
                        {{ $seller->code }} — {{ $seller->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin:0;{{ $partyType === 'seller' || ! $partyType ? 'display:none' : '' }}" id="customer-field">
            <label for="customer_id">Cliente</label>
            <select id="customer_id" name="customer_id">
                <option value="">—</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((int) $customerId === (int) $customer->id)>
                        {{ $customer->code }} — {{ $customer->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <button class="btn" type="submit">Ver entregas</button>
    </form>
</div>

@if ($partyType && ($sellerId || $customerId))
    <div class="meta" style="margin-bottom:1rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
        <div class="card">Consignatario<strong>{{ $partyName ?? '—' }}</strong></div>
        <div class="card">Entregas con saldo<strong>{{ $openConsignments->count() }}</strong></div>
        <div class="card">Saldo total<strong style="color:var(--danger)">{{ money($totalBalance) }}</strong></div>
    </div>

    @if ($openConsignments->isEmpty())
        <div class="card">
            <p class="muted" style="margin:0">No hay entregas abiertas o parciales con saldo para este consignatario.</p>
        </div>
    @else
        <form method="POST" action="{{ route('consignments.settle.store') }}" id="settle-form">
            @csrf
            <input type="hidden" name="party_type" value="{{ $partyType }}">
            @if ($partyType === 'seller')
                <input type="hidden" name="seller_id" value="{{ $sellerId }}">
            @else
                <input type="hidden" name="customer_id" value="{{ $customerId }}">
            @endif

            <div class="card" style="margin-bottom:1rem">
                <div class="grid-2" style="align-items:end;margin-bottom:1rem">
                    <div class="field" style="margin:0">
                        <label for="apply_total">Monto total a aplicar (opcional)</label>
                        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                            <input id="apply_total" type="number" min="0.01" step="0.01" value="{{ number_format($totalBalance, 2, '.', '') }}" placeholder="Ej. {{ number_format($totalBalance, 2, '.', '') }}" style="flex:1;min-width:140px">
                            <button class="btn" type="button" id="btn-apply-fifo">Aplicar monto (auto)</button>
                            <button class="btn btn-secondary" type="button" id="btn-fill-balances">Llenar todos los saldos</button>
                            <button class="btn btn-secondary" type="button" id="btn-clear">Limpiar</button>
                        </div>
                        <p class="muted" style="margin:.4rem 0 0;font-size:.85rem">
                            Escribe el monto total y pulsa <strong>Aplicar monto (auto)</strong>: se reparte solo en las entregas más antiguas.
                            O usa <strong>Llenar todos los saldos</strong> para pagar todo.
                        </p>
                    </div>
                    <div class="field" style="margin:0">
                        <label>Total a liquidar</label>
                        <strong id="settle-total-display" style="font-size:1.35rem">{{ money(0) }}</strong>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width:2.5rem">
                                <input type="checkbox" id="select-all" checked title="Seleccionar todas">
                            </th>
                            <th>Número</th>
                            <th>Entrega</th>
                            <th>Estado</th>
                            <th>Saldo</th>
                            <th>Monto a pagar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($openConsignments as $index => $consignment)
                            @php
                                $balance = (float) $consignment->balance_with_vat;
                                $oldAmount = old('allocations.'.$index.'.amount', number_format($balance, 2, '.', ''));
                                $oldSelected = old('allocations.'.$index.'.selected', '1');
                            @endphp
                            <tr data-balance="{{ number_format($balance, 2, '.', '') }}">
                                <td>
                                    <input type="hidden" name="allocations[{{ $index }}][consignment_id]" value="{{ $consignment->id }}">
                                    <input
                                        type="checkbox"
                                        class="row-select"
                                        name="allocations[{{ $index }}][selected]"
                                        value="1"
                                        @checked($oldSelected)
                                    >
                                </td>
                                <td>
                                    <a href="{{ route('consignments.show', $consignment) }}" target="_blank">{{ $consignment->number }}</a>
                                </td>
                                <td>{{ $consignment->delivered_at?->format('d/m/Y') }}</td>
                                <td>{{ $consignment->statusLabel() }}</td>
                                <td>{{ money($balance) }}</td>
                                <td>
                                    <input
                                        type="number"
                                        class="row-amount"
                                        name="allocations[{{ $index }}][amount]"
                                        min="0"
                                        max="{{ number_format($balance, 2, '.', '') }}"
                                        step="0.01"
                                        value="{{ $oldAmount }}"
                                        style="width:7.5rem"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="grid-2">
                    <div class="field">
                        <label for="paid_at">Fecha del pago *</label>
                        <input id="paid_at" type="datetime-local" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d\\TH:i')) }}" required>
                    </div>
                    <div class="field">
                        <label for="method">Método *</label>
                        <select id="method" name="method" required>
                            @foreach ($paymentMethods as $key => $label)
                                <option value="{{ $key }}" @selected(old('method', 'cash') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="notes">Notas</label>
                    <input id="notes" type="text" name="notes" value="{{ old('notes', 'Liquidación múltiple') }}" maxlength="2000">
                </div>
                <button class="btn" type="submit" id="settle-submit">Registrar liquidación</button>
            </div>
        </form>
    @endif
@else
    <div class="card">
        <p class="muted" style="margin:0">Elige vendedor o cliente y pulsa <strong>Ver entregas</strong>.</p>
    </div>
@endif

<script>
(() => {
    const partyType = document.getElementById('party_type');
    const sellerField = document.getElementById('seller-field');
    const customerField = document.getElementById('customer-field');
    const sellerSelect = document.getElementById('seller_id');
    const customerSelect = document.getElementById('customer_id');

    function syncPartyFields() {
        const type = partyType?.value;
        if (sellerField) sellerField.style.display = type === 'seller' ? '' : 'none';
        if (customerField) customerField.style.display = type === 'customer' ? '' : 'none';
        if (type !== 'seller' && sellerSelect) sellerSelect.value = '';
        if (type !== 'customer' && customerSelect) customerSelect.value = '';
    }

    partyType?.addEventListener('change', syncPartyFields);

    const form = document.getElementById('settle-form');
    if (!form) return;

    const rows = () => Array.from(form.querySelectorAll('tbody tr[data-balance]'));
    const moneyFmt = (n) => {
        try {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n);
        } catch (_) {
            return '$' + n.toFixed(2);
        }
    };

    function updateTotal() {
        let total = 0;
        rows().forEach((tr) => {
            const selected = tr.querySelector('.row-select')?.checked;
            const amount = parseFloat(tr.querySelector('.row-amount')?.value || '0');
            if (selected && amount > 0) total += amount;
        });
        const el = document.getElementById('settle-total-display');
        if (el) el.textContent = moneyFmt(Math.round(total * 100) / 100);
    }

    function setRow(tr, selected, amount) {
        const cb = tr.querySelector('.row-select');
        const input = tr.querySelector('.row-amount');
        if (cb) cb.checked = selected;
        if (input) {
            input.value = selected ? (Math.round(amount * 100) / 100).toFixed(2) : '0.00';
            input.disabled = !selected;
        }
    }

    document.getElementById('select-all')?.addEventListener('change', (e) => {
        const on = e.target.checked;
        rows().forEach((tr) => {
            const bal = parseFloat(tr.dataset.balance || '0');
            setRow(tr, on, on ? bal : 0);
        });
        updateTotal();
    });

    document.getElementById('btn-fill-balances')?.addEventListener('click', () => {
        rows().forEach((tr) => {
            const bal = parseFloat(tr.dataset.balance || '0');
            setRow(tr, true, bal);
        });
        const all = document.getElementById('select-all');
        if (all) all.checked = true;
        updateTotal();
    });

    document.getElementById('btn-clear')?.addEventListener('click', () => {
        rows().forEach((tr) => setRow(tr, false, 0));
        const all = document.getElementById('select-all');
        if (all) all.checked = false;
        updateTotal();
    });

    document.getElementById('btn-apply-fifo')?.addEventListener('click', () => {
        let remaining = parseFloat(document.getElementById('apply_total')?.value || '0');
        if (!(remaining > 0)) {
            alert('Indica un monto total a aplicar.');
            return;
        }
        rows().forEach((tr) => setRow(tr, false, 0));
        rows().forEach((tr) => {
            if (remaining <= 0.009) return;
            const bal = parseFloat(tr.dataset.balance || '0');
            const take = Math.min(bal, remaining);
            if (take > 0.009) {
                setRow(tr, true, take);
                remaining = Math.round((remaining - take) * 100) / 100;
            }
        });
        updateTotal();
    });

    form.addEventListener('change', (e) => {
        if (e.target.classList.contains('row-select')) {
            const tr = e.target.closest('tr');
            const bal = parseFloat(tr?.dataset.balance || '0');
            if (e.target.checked) {
                const input = tr.querySelector('.row-amount');
                if (input && !(parseFloat(input.value) > 0)) input.value = bal.toFixed(2);
                if (input) input.disabled = false;
            } else {
                const input = tr.querySelector('.row-amount');
                if (input) {
                    input.value = '0.00';
                    input.disabled = true;
                }
            }
            updateTotal();
        }
        if (e.target.classList.contains('row-amount')) updateTotal();
    });

    form.addEventListener('input', (e) => {
        if (e.target.classList.contains('row-amount')) updateTotal();
    });

    form.addEventListener('submit', (e) => {
        let total = 0;
        let count = 0;
        rows().forEach((tr) => {
            const selected = tr.querySelector('.row-select')?.checked;
            const amount = parseFloat(tr.querySelector('.row-amount')?.value || '0');
            if (selected && amount > 0) {
                count += 1;
                total += amount;
            }
        });
        if (count === 0) {
            e.preventDefault();
            alert('Selecciona al menos una entrega con monto.');
            return;
        }
        if (!confirm(`¿Registrar liquidación de ${count} entrega(s) por ${moneyFmt(total)}?`)) {
            e.preventDefault();
        }
    });

    rows().forEach((tr) => {
        const cb = tr.querySelector('.row-select');
        const input = tr.querySelector('.row-amount');
        if (input && cb && !cb.checked) input.disabled = true;
    });
    updateTotal();
})();
</script>
@endsection
