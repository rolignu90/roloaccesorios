@extends('layouts.app')

@section('title', 'Nueva liquidación')

@section('content')
<div class="topbar">
    <div>
        <h1>Nueva liquidación de vendedor</h1>
        <p class="muted">Solo ventas <strong>entregadas</strong> · semanal o mensual · compensación opcional a consignaciones</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('sales.seller-settlements.index') }}">Historial</a>
</div>

@if ($errors->any())
    <div class="flash" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-bottom:1rem">
        {{ $errors->first() }}
    </div>
@endif
@if ($error)
    <div class="flash" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-bottom:1rem">{{ $error }}</div>
@endif

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('sales.seller-settlements.create') }}" id="preview-form" style="flex-wrap:wrap;align-items:end">
        <div class="field" style="margin:0">
            <label for="seller_id">Vendedor *</label>
            <select id="seller_id" name="seller_id" required>
                <option value="">—</option>
                @foreach ($sellers as $seller)
                    <option
                        value="{{ $seller->id }}"
                        data-type="{{ $seller->type }}"
                        data-salary="{{ $seller->salary_amount }}"
                        data-commission="{{ $seller->commission_percent }}"
                        @selected((int) $sellerId === (int) $seller->id)
                    >
                        {{ $seller->code }} — {{ $seller->name }} ({{ $seller->typeLabel() }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin:0">
            <label for="period_type">Período *</label>
            <select id="period_type" name="period_type" required>
                <option value="week" @selected($periodType === 'week')>Semanal</option>
                <option value="month" @selected($periodType === 'month')>Mensual</option>
                <option value="custom" @selected($periodType === 'custom')>Personalizado</option>
            </select>
        </div>
        <div class="field" style="margin:0" id="anchor-field">
            <label for="anchor_date">Fecha de referencia</label>
            <input id="anchor_date" type="date" name="anchor_date" value="{{ $anchorDate }}">
            <p class="muted" style="margin:.35rem 0 0;font-size:.78rem">Semana (lun–dom) o mes de esa fecha.</p>
        </div>
        <div class="field" style="margin:0;display:none" id="from-field">
            <label for="from">Desde</label>
            <input id="from" type="date" name="from" value="{{ $from }}">
        </div>
        <div class="field" style="margin:0;display:none" id="to-field">
            <label for="to">Hasta</label>
            <input id="to" type="date" name="to" value="{{ $to }}">
        </div>
        <button class="btn" type="submit">Calcular</button>
    </form>
</div>

@if ($preview)
    <div class="meta" style="margin-bottom:1rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
        <div class="card">Vendedor<strong>{{ $preview['seller']->code }} — {{ $preview['seller']->name }}</strong></div>
        <div class="card">Tipo<strong>{{ $preview['seller']->typeLabel() }}</strong></div>
        <div class="card">Período<strong>{{ $preview['period_from']->format('d/m/Y') }} – {{ $preview['period_to']->format('d/m/Y') }}</strong></div>
        <div class="card">A pagar<strong style="color:var(--signal)">{{ money($preview['amount_due']) }}</strong></div>
        <div class="card">A consignación<strong>{{ money($preview['applied_to_consignments']) }}</strong></div>
        <div class="card">Efectivo a pagar<strong>{{ money($preview['cash_paid']) }}</strong></div>
    </div>

    <div class="card" style="margin-bottom:1rem">
        <p class="muted" style="margin-top:0">{{ $preview['formula'] }}</p>
        <div class="grid-2">
            <div>
                <p><strong>Ventas entregadas:</strong> {{ $preview['sales_count'] }}</p>
                <p>Total ventas: {{ money($preview['sales_total']) }}</p>
                <p>COGS ventas: {{ money($preview['sales_cogs']) }}</p>
                <p>Margen real c/IVA: {{ money($preview['sales_real_margin_with_vat']) }}</p>
            </div>
            <div>
                <p><strong>Devoluciones:</strong> {{ $preview['returns_count'] }}</p>
                <p>COGS devoluciones: {{ money($preview['returns_cogs']) }}</p>
                <p>Costo envío/COD devoluciones: {{ money($preview['returns_carrier_cost']) }}</p>
                <p>Costo total devoluciones: {{ money($preview['returns_cost_total']) }}</p>
            </div>
        </div>
        @if ($preview['seller']->isInternal())
            <hr style="border:0;border-top:1px solid var(--line);margin:1rem 0">
            <p>Salario: {{ money($preview['salary_amount']) }}</p>
            <p>Comisión {{ number_format((float) $preview['commission_percent'], 2) }}% de {{ money($preview['commission_base']) }} = {{ money($preview['commission_amount']) }}</p>
        @else
            <hr style="border:0;border-top:1px solid var(--line);margin:1rem 0">
            <p>
                {{ money($preview['sales_real_margin_with_vat']) }}
                − {{ money($preview['returns_cost_total']) }}
                = <strong>{{ money($preview['amount_due']) }}</strong>
            </p>
        @endif

        <hr style="border:0;border-top:1px solid var(--line);margin:1rem 0">
        <p>
            <strong>Cliente vinculado:</strong>
            @if ($preview['linked_customer'])
                {{ $preview['linked_customer']->code }} — {{ $preview['linked_customer']->name }}
            @else
                <span class="muted">Ninguno (edita el vendedor para vincular)</span>
            @endif
        </p>
        <p><strong>Saldo consignaciones abiertas:</strong> {{ money($preview['consignment_balance']) }} ({{ $preview['open_consignments']->count() }} entregas)</p>
    </div>

    @if ($preview['max_apply_to_consignments'] > 0)
        @php
            $offsetMode = old('offset_mode', request('offset_mode', 'auto'));
            if (! in_array($offsetMode, ['auto', 'none', 'custom'], true)) {
                $offsetMode = 'auto';
            }
            $displayApplied = $offsetMode === 'none'
                ? 0.0
                : (float) $preview['applied_to_consignments'];
            $displayCash = round((float) $preview['amount_due'] - $displayApplied, 2);
        @endphp
        <div class="card" style="margin-bottom:1rem;border:2px solid var(--signal)" id="offset-card"
             data-max-apply="{{ number_format($preview['max_apply_to_consignments'], 2, '.', '') }}"
             data-amount-due="{{ number_format($preview['amount_due'], 2, '.', '') }}">
            <h2 style="margin-top:0;font-size:1.15rem">¿Compensar con consignaciones?</h2>
            <p class="muted" style="margin:.25rem 0 1rem">
                Saldo abierto: {{ money($preview['consignment_balance']) }} en {{ $preview['open_consignments']->count() }} entregas.
                Puedes usarlo para bajar la deuda o pagar la liquidación completa en efectivo.
            </p>

            <div class="field" style="margin-bottom:1rem">
                <label style="display:flex;align-items:flex-start;gap:.5rem;margin-bottom:.65rem;font-weight:500">
                    <input type="radio" name="offset_mode" value="auto" form="confirm-settle-form" data-offset-mode @checked($offsetMode === 'auto')>
                    <span>
                        Sí, compensar automáticamente
                        <span class="muted" style="display:block;font-weight:400;margin-top:.15rem">
                            Aplica {{ money($preview['max_apply_to_consignments']) }} a las consignaciones más antiguas.
                        </span>
                    </span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:.5rem;margin-bottom:.65rem;font-weight:500">
                    <input type="radio" name="offset_mode" value="none" form="confirm-settle-form" data-offset-mode @checked($offsetMode === 'none')>
                    <span>
                        No compensar
                        <span class="muted" style="display:block;font-weight:400;margin-top:.15rem">
                            No toca consignaciones. Todo el monto ({{ money($preview['amount_due']) }}) queda como efectivo a pagar.
                        </span>
                    </span>
                </label>
                <label style="display:flex;align-items:flex-start;gap:.5rem;font-weight:500">
                    <input type="radio" name="offset_mode" value="custom" form="confirm-settle-form" data-offset-mode @checked($offsetMode === 'custom')>
                    <span>
                        Compensar un monto distinto
                        <span class="muted" style="display:block;font-weight:400;margin-top:.15rem">
                            Eliges cuánto aplicar a consignaciones (máx. {{ money($preview['max_apply_to_consignments']) }}).
                        </span>
                    </span>
                </label>
            </div>

            <div class="field" id="custom-offset-field" style="{{ $offsetMode === 'custom' ? '' : 'display:none' }}">
                <label for="custom_apply_amount">Monto a consignación</label>
                <input
                    id="custom_apply_amount"
                    type="number"
                    min="0"
                    max="{{ number_format($preview['max_apply_to_consignments'], 2, '.', '') }}"
                    step="0.01"
                    value="{{ old('custom_apply_amount', request('apply_to_consignments', $preview['applied_to_consignments'])) }}"
                >
            </div>

            <p style="margin:1rem 0 .5rem">
                A consignación: <strong id="offset-applied-label">{{ money($displayApplied) }}</strong>
                · Efectivo: <strong id="offset-cash-label">{{ money($displayCash) }}</strong>
            </p>

            <div id="planned-allocations-wrap" style="{{ $displayApplied > 0.009 ? '' : 'display:none' }}">
                <p class="muted" style="margin:0 0 .5rem">Reparto automático (más antiguas primero):</p>
                <table>
                    <thead>
                        <tr>
                            <th>Consignación</th>
                            <th>Saldo</th>
                            <th>Se aplicará</th>
                        </tr>
                    </thead>
                    <tbody id="planned-allocations-body"
                           data-rows='@json($preview['planned_allocations'])'
                           data-max-apply="{{ number_format($preview['max_apply_to_consignments'], 2, '.', '') }}">
                        @foreach ($preview['planned_allocations'] as $row)
                            <tr>
                                <td>{{ $row['number'] }}</td>
                                <td>{{ money($row['balance']) }}</td>
                                <td><strong>{{ money($row['amount']) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($preview['linked_customer'] && $preview['consignment_balance'] <= 0)
        <div class="card" style="margin-bottom:1rem">
            <p class="muted" style="margin:0">El cliente vinculado no tiene saldo de consignación pendiente. Toda la liquidación quedará como efectivo a pagar.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('sales.seller-settlements.store') }}" class="card" style="margin-bottom:1rem" id="confirm-settle-form">
        @csrf
        <input type="hidden" name="seller_id" value="{{ $preview['seller']->id }}">
        <input type="hidden" name="period_type" value="{{ $preview['period_type'] }}">
        <input type="hidden" name="anchor_date" value="{{ $anchorDate }}">
        <input type="hidden" name="from" value="{{ $preview['period_from']->toDateString() }}">
        <input type="hidden" name="to" value="{{ $preview['period_to']->toDateString() }}">
        <input type="hidden" name="apply_to_consignments" id="apply_to_consignments_hidden" value="{{ number_format($displayApplied ?? $preview['applied_to_consignments'], 2, '.', '') }}">

        @if ($preview['seller']->isInternal())
            <div class="grid-2">
                <div class="field">
                    <label for="salary_amount">Salario de esta liquidación</label>
                    <input id="salary_amount" type="number" min="0" step="0.01" name="salary_amount" value="{{ old('salary_amount', $preview['salary_amount']) }}">
                </div>
                <div class="field">
                    <label for="commission_percent">Comisión %</label>
                    <input id="commission_percent" type="number" min="0" max="100" step="0.01" name="commission_percent" value="{{ old('commission_percent', $preview['commission_percent']) }}">
                </div>
            </div>
        @endif

        <div class="grid-2">
            <div class="field">
                <label for="paid_at">Fecha de pago</label>
                <input id="paid_at" type="datetime-local" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d\\TH:i')) }}">
            </div>
            <div class="field">
                <label for="payment_method">Método (solo la parte en efectivo)</label>
                <select id="payment_method" name="payment_method">
                    <option value="">—</option>
                    @foreach ($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field">
            <label for="notes">Notas</label>
            <input id="notes" type="text" name="notes" value="{{ old('notes') }}">
        </div>
        <p id="confirm-summary">
            Total liquidación <strong>{{ money($preview['amount_due']) }}</strong>
            · a consignación <strong id="confirm-applied">{{ money($displayApplied ?? $preview['applied_to_consignments']) }}</strong>
            · efectivo <strong id="confirm-cash">{{ money($displayCash ?? $preview['cash_paid']) }}</strong>
        </p>
        <button class="btn" type="submit" id="confirm-settle-btn" @disabled($preview['sales_count'] === 0 && $preview['returns_count'] === 0 && abs($preview['amount_due']) < 0.01)>
            Confirmar liquidación
        </button>
    </form>

    <div class="grid-2">
        <div class="card">
            <h2 style="margin-top:0;font-size:1.1rem">Ventas entregadas del período ({{ $preview['sales_count'] }})</h2>
            <table>
                <thead>
                    <tr>
                        <th>Venta</th>
                        <th>Total</th>
                        <th>Margen real</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($preview['sales'] as $sale)
                        <tr>
                            <td>
                                <a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a>
                                <div class="muted">{{ $sale->sold_at?->format('d/m/Y') }} · {{ $sale->customer?->name }}</div>
                            </td>
                            <td>{{ money($sale->total) }}</td>
                            <td>{{ money($sale->realMarginWithVat()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Sin ventas entregadas pendientes en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card">
            <h2 style="margin-top:0;font-size:1.1rem">Devoluciones ({{ $preview['returns_count'] }})</h2>
            <table>
                <thead>
                    <tr>
                        <th>Venta</th>
                        <th>COGS</th>
                        <th>Envío/COD</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($preview['returns'] as $sale)
                        <tr>
                            <td>
                                <a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a>
                                <div class="muted">{{ optional($sale->status_changed_at ?? $sale->sold_at)->format('d/m/Y') }}</div>
                            </td>
                            <td>{{ money($sale->cogs_total) }}</td>
                            <td>{{ money($sale->carrierCostTotal()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">Sin devoluciones pendientes en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

<script>
(() => {
    const period = document.getElementById('period_type');
    const anchor = document.getElementById('anchor-field');
    const fromField = document.getElementById('from-field');
    const toField = document.getElementById('to-field');
    const syncPeriod = () => {
        const custom = period?.value === 'custom';
        if (anchor) anchor.style.display = custom ? 'none' : '';
        if (fromField) fromField.style.display = custom ? '' : 'none';
        if (toField) toField.style.display = custom ? '' : 'none';
    };
    period?.addEventListener('change', syncPeriod);
    syncPeriod();

    const card = document.getElementById('offset-card');
    if (!card) return;

    const moneyFmt = (n) => {
        try {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n);
        } catch (_) {
            return '$' + Number(n).toFixed(2);
        }
    };

    const maxApply = parseFloat(card.dataset.maxApply || '0');
    const amountDue = parseFloat(card.dataset.amountDue || '0');
    const hidden = document.getElementById('apply_to_consignments_hidden');
    const customField = document.getElementById('custom-offset-field');
    const customInput = document.getElementById('custom_apply_amount');
    const appliedLabel = document.getElementById('offset-applied-label');
    const cashLabel = document.getElementById('offset-cash-label');
    const confirmApplied = document.getElementById('confirm-applied');
    const confirmCash = document.getElementById('confirm-cash');
    const confirmBtn = document.getElementById('confirm-settle-btn');
    const planWrap = document.getElementById('planned-allocations-wrap');
    const planBody = document.getElementById('planned-allocations-body');
    let planRows = [];
    try {
        planRows = JSON.parse(planBody?.dataset?.rows || '[]');
    } catch (_) {
        planRows = [];
    }

    const planForAmount = (amount) => {
        let remaining = Math.round(amount * 100) / 100;
        const out = [];
        planRows.forEach((row) => {
            if (remaining <= 0.009) return;
            const bal = Number(row.balance || 0);
            const take = Math.min(bal, remaining);
            if (take > 0.009) {
                out.push({ ...row, amount: Math.round(take * 100) / 100 });
                remaining = Math.round((remaining - take) * 100) / 100;
            }
        });
        // If custom amount exceeds the precomputed plan rows total, keep what we have;
        // server will re-plan on submit.
        return out;
    };

    const renderPlan = (amount) => {
        if (!planBody || !planWrap) return;
        if (amount <= 0.009) {
            planWrap.style.display = 'none';
            return;
        }
        planWrap.style.display = '';
        const rows = planForAmount(amount);
        planBody.innerHTML = rows.map((row) => (
            `<tr><td>${row.number}</td><td>${moneyFmt(row.balance)}</td><td><strong>${moneyFmt(row.amount)}</strong></td></tr>`
        )).join('') || '<tr><td colspan="3" class="muted">Sin filas para ese monto.</td></tr>';
    };

    const syncOffset = () => {
        const mode = document.querySelector('[data-offset-mode]:checked')?.value || 'auto';
        if (customField) customField.style.display = mode === 'custom' ? '' : 'none';

        let applied = 0;
        if (mode === 'auto') applied = maxApply;
        else if (mode === 'none') applied = 0;
        else {
            applied = parseFloat(customInput?.value || '0');
            if (!(applied >= 0)) applied = 0;
            if (applied > maxApply) applied = maxApply;
        }
        applied = Math.round(applied * 100) / 100;
        const cash = Math.round(Math.max(0, amountDue - applied) * 100) / 100;

        if (hidden) hidden.value = applied.toFixed(2);
        if (appliedLabel) appliedLabel.textContent = moneyFmt(applied);
        if (cashLabel) cashLabel.textContent = moneyFmt(cash);
        if (confirmApplied) confirmApplied.textContent = moneyFmt(applied);
        if (confirmCash) confirmCash.textContent = moneyFmt(cash);
        if (confirmBtn) {
            confirmBtn.textContent = applied > 0.009
                ? `Confirmar · compensar ${moneyFmt(applied)} en consignaciones`
                : `Confirmar liquidación · ${moneyFmt(amountDue)} (sin compensar)`;
        }
        renderPlan(mode === 'auto' ? maxApply : applied);
    };

    document.querySelectorAll('[data-offset-mode]').forEach((el) => {
        el.addEventListener('change', syncOffset);
    });
    customInput?.addEventListener('input', syncOffset);
    syncOffset();
})();
</script>
@endsection
