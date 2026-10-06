@php
    $seller = $seller ?? null;
    $prefixValue = old('sale_prefix', $seller?->sale_prefix);
    if (is_string($prefixValue) && $prefixValue !== '' && ! str_ends_with($prefixValue, '-')) {
        $prefixValue .= '-';
    }
@endphp

<div class="grid-2">
    <div class="field">
        <label for="code">Código</label>
        <input
            id="code"
            type="text"
            name="code_display"
            value="{{ $seller?->code ?? ($nextCode ?? '') }}"
            readonly
        >
        <p class="muted" style="margin:.35rem 0 0">Se asigna automáticamente (VEN-####).</p>
    </div>
    <div class="field">
        <label for="sale_prefix">Prefijo de venta *</label>
        <input
            id="sale_prefix"
            type="text"
            name="sale_prefix"
            value="{{ $prefixValue }}"
            required
            maxlength="10"
            placeholder="M-"
            autocomplete="off"
            style="text-transform:uppercase"
        >
        <p class="muted" style="margin:.35rem 0 0">Ej. <strong>M-</strong> → las ventas saldrán como M-{{ now()->format('Ymd') }}-0001. Debe ser único.</p>
    </div>
</div>

<div class="field">
    <label for="name">Nombre *</label>
    <input id="name" type="text" name="name" value="{{ old('name', $seller?->name) }}" required autocomplete="off">
</div>

<div class="grid-2">
    <div class="field">
        <label for="phone">Teléfono</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone', $seller?->phone) }}" autocomplete="off">
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $seller?->email) }}" autocomplete="off">
    </div>
</div>

<div class="field">
    <label for="notes">Notas</label>
    <textarea id="notes" name="notes">{{ old('notes', $seller?->notes) }}</textarea>
</div>

<div class="card" style="margin:1rem 0;padding:1rem;background:#f9fafb;border:2px solid var(--signal)">
    <strong>Cliente vinculado</strong>
    <p class="muted" style="margin:.35rem 0 1rem">
        Si el vendedor también es cliente (ej. Fatima), vincúlalo para compensar la liquidación contra su deuda de consignación.
    </p>
    @if ($seller?->customer)
        <p style="margin:0 0 .75rem">
            Ahora:
            <a href="{{ route('sales.customers.show', $seller->customer) }}">
                <strong>{{ $seller->customer->code }} — {{ $seller->customer->name }}</strong>
            </a>
        </p>
    @endif
    <div class="field" style="margin:0">
        <label for="customer_id">Elegir cliente</label>
        <select id="customer_id" name="customer_id">
            <option value="">— Sin vincular —</option>
            @foreach (($customers ?? collect()) as $customer)
                <option value="{{ $customer->id }}" @selected((string) old('customer_id', $seller?->customer_id) === (string) $customer->id)>
                    {{ $customer->code }} — {{ $customer->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="card" style="margin:1rem 0;padding:1rem;background:#f9fafb">
    <strong>Liquidación</strong>
    <p class="muted" style="margin:.35rem 0 1rem">
        Externos: se liquida margen real c/IVA menos costo de devoluciones.
        Internos: salario del período + comisión % sobre ventas.
    </p>
    <div class="field">
        <label>Tipo *</label>
        <div style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:.4rem">
            <label style="display:flex;align-items:center;gap:.4rem;font-weight:500">
                <input type="radio" name="type" value="external" @checked(old('type', $seller?->type ?? 'external') === 'external') data-seller-type>
                Externo
            </label>
            <label style="display:flex;align-items:center;gap:.4rem;font-weight:500">
                <input type="radio" name="type" value="internal" @checked(old('type', $seller?->type ?? 'external') === 'internal') data-seller-type>
                Interno
            </label>
        </div>
    </div>
    <div class="grid-2" id="internal-pay-fields" style="{{ old('type', $seller?->type ?? 'external') === 'internal' ? '' : 'display:none' }}">
        <div class="field">
            <label for="salary_amount">Salario base del período (USD)</label>
            <input id="salary_amount" type="number" min="0" step="0.01" name="salary_amount" value="{{ old('salary_amount', $seller?->salary_amount) }}">
            <p class="muted" style="margin:.35rem 0 0">Se usa como default al liquidar (puedes cambiarlo en cada liquidación).</p>
        </div>
        <div class="field">
            <label for="commission_percent">Comisión % sobre ventas</label>
            <input id="commission_percent" type="number" min="0" max="100" step="0.01" name="commission_percent" value="{{ old('commission_percent', $seller?->commission_percent) }}">
            <p class="muted" style="margin:.35rem 0 0">% del total de ventas c/IVA del período.</p>
        </div>
    </div>
</div>

<div class="field">
    <input type="hidden" name="is_active" value="0">
    <label>
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $seller?->is_active ?? true))>
        Activo
    </label>
</div>

<script>
(() => {
    const sync = () => {
        const type = document.querySelector('[data-seller-type]:checked')?.value || 'external';
        const box = document.getElementById('internal-pay-fields');
        if (box) box.style.display = type === 'internal' ? '' : 'none';
    };
    document.querySelectorAll('[data-seller-type]').forEach((el) => el.addEventListener('change', sync));
    sync();
})();
</script>
