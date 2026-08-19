@php($carrier = $carrier ?? null)
<div class="grid-2">
    <div class="field">
        <label for="name">Nombre *</label>
        <input id="name" type="text" name="name" value="{{ old('name', $carrier?->name) }}" required placeholder="Ej. Cargo Expreso">
    </div>
    <div class="field">
        <label for="code">Código</label>
        <input id="code" type="text" name="code" value="{{ old('code', $carrier?->code) }}" placeholder="Ej. CE">
    </div>
</div>

<div class="grid-2">
    <div class="field">
        <label for="shipping_cost">Costo de envío (USD) *</label>
        <input id="shipping_cost" type="number" min="0" step="0.01" name="shipping_cost" value="{{ old('shipping_cost', $carrier?->shipping_cost ?? '0') }}" required>
        <p class="muted" style="margin:.35rem 0 0">Lo que te cobra la empresa por el envío (tu costo).</p>
    </div>
    <div class="field">
        <label for="commission_type">Comisión por cobro de efectivo *</label>
        <select id="commission_type" name="commission_type" required data-commission-type>
            @foreach (\App\Models\ShippingCarrier::COMMISSION_TYPES as $key => $label)
                <option value="{{ $key }}" @selected(old('commission_type', $carrier?->commission_type ?? 'fixed') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="field">
    <label for="commission_value" id="commission-value-label">Valor de comisión *</label>
    <input id="commission_value" type="number" min="0" step="0.01" name="commission_value" value="{{ old('commission_value', $carrier?->commission_value ?? '0') }}" required>
    <p class="muted" style="margin:.35rem 0 0" id="commission-value-help">Monto fijo en USD, o porcentaje del total c/IVA de la venta.</p>
</div>

<div class="field">
    <label for="notes">Notas</label>
    <textarea id="notes" name="notes">{{ old('notes', $carrier?->notes) }}</textarea>
</div>

<div class="field">
    <input type="hidden" name="is_active" value="0">
    <label style="display:flex;align-items:center;gap:.5rem;font-weight:500;cursor:pointer">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $carrier?->is_active ?? true))>
        Activa
    </label>
</div>

<div class="card" style="margin:1rem 0;padding:1rem;background:#f9fafb" data-sistrack-box>
    <div class="field" style="margin-bottom:.75rem">
        <input type="hidden" name="sistrack_enabled" value="0">
        <label style="display:flex;align-items:center;gap:.5rem;font-weight:500;cursor:pointer">
            <input
                type="checkbox"
                name="sistrack_enabled"
                value="1"
                data-sistrack-toggle
                @checked(old('sistrack_enabled', $carrier?->sistrack_enabled ?? false))
            >
            Integración Sistrack (crear etiquetas / órdenes)
        </label>
        <p class="muted" style="margin:.35rem 0 0">Actívalo solo para empresas como Express El Salvador.</p>
    </div>

    <div data-sistrack-fields @style(['display:none' => ! old('sistrack_enabled', $carrier?->sistrack_enabled ?? false)])>
        <div class="grid-2">
            <div class="field">
                <label for="sistrack_base_url">URL Sistrack</label>
                <input
                    id="sistrack_base_url"
                    type="url"
                    name="sistrack_base_url"
                    value="{{ old('sistrack_base_url', $carrier?->sistrack_base_url ?: 'https://expresselsalvador.sistrack.net') }}"
                    placeholder="https://expresselsalvador.sistrack.net"
                >
            </div>
            <div class="field">
                <label for="sistrack_sender_id">Sender ID</label>
                <input
                    id="sistrack_sender_id"
                    type="number"
                    min="1"
                    name="sistrack_sender_id"
                    value="{{ old('sistrack_sender_id', $carrier?->sistrack_sender_id ?: 67306) }}"
                >
            </div>
        </div>
        <div class="grid-2">
            <div class="field">
                <label for="sistrack_email">Correo Sistrack</label>
                <input
                    id="sistrack_email"
                    type="email"
                    name="sistrack_email"
                    value="{{ old('sistrack_email', $carrier?->sistrack_email) }}"
                    autocomplete="off"
                >
            </div>
            <div class="field">
                <label for="sistrack_password">Contraseña Sistrack</label>
                <input
                    id="sistrack_password"
                    type="password"
                    name="sistrack_password"
                    value=""
                    autocomplete="new-password"
                    placeholder="{{ $carrier?->sistrack_password ? '•••••••• (dejar vacío para no cambiar)' : '' }}"
                >
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const typeSelect = document.querySelector('[data-commission-type]');
    const label = document.getElementById('commission-value-label');
    const help = document.getElementById('commission-value-help');
    const input = document.getElementById('commission_value');
    const sync = () => {
        const isPercent = typeSelect?.value === 'percent';
        if (label) label.textContent = isPercent ? 'Porcentaje de comisión *' : 'Monto fijo de comisión (USD) *';
        if (help) help.textContent = isPercent
            ? 'Ej. 3 = 3% del total c/IVA de la venta (productos + envío cobrado al cliente).'
            : 'Monto fijo en USD por cobro de efectivo (COD).';
        if (input) input.step = isPercent ? '0.0001' : '0.01';
    };
    typeSelect?.addEventListener('change', sync);
    sync();

    const toggle = document.querySelector('[data-sistrack-toggle]');
    const fields = document.querySelector('[data-sistrack-fields]');
    const syncSistrack = () => {
        if (!fields) return;
        fields.style.display = toggle?.checked ? '' : 'none';
    };
    toggle?.addEventListener('change', syncSistrack);
    syncSistrack();
})();
</script>
