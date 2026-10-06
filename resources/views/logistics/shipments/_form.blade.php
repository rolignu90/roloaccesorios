@php
    $shipment = $shipment ?? null;
    $editing = $shipment !== null;
    $lockCarrier = $editing && $shipment->isSistrackSent();
    $departments = \App\Support\ElSalvadorGeo::departmentNames();
    $selectedDepartment = old('department', $shipment?->department);
    $selectedMunicipality = old('municipality', $shipment?->municipality);
    $municipalities = filled($selectedDepartment)
        ? \App\Support\ElSalvadorGeo::municipalityNames($selectedDepartment)
        : [];
    $selectedCarrierId = old('shipping_carrier_id', $shipment?->shipping_carrier_id);
@endphp

<div class="grid-2">
    <div class="field">
        <label for="logistics_client_id">Empresa *</label>
        <select id="logistics_client_id" name="logistics_client_id" required>
            <option value="">—</option>
            @foreach ($clients as $client)
                <option
                    value="{{ $client->id }}"
                    data-carrier="{{ $client->default_shipping_carrier_id }}"
                    data-commission-type="{{ $client->commission_type }}"
                    data-commission-value="{{ $client->commission_value }}"
                    @selected((string) old('logistics_client_id', $shipment?->logistics_client_id ?? $selectedClientId ?? null) === (string) $client->id)
                >
                    {{ $client->code }} — {{ $client->name }} ({{ $client->commissionSummary() }})
                </option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="shipping_carrier_id">Empresa de envío</label>
        @if ($lockCarrier)
            <input type="hidden" name="shipping_carrier_id" value="{{ $shipment->shipping_carrier_id }}">
        @endif
        <select id="shipping_carrier_id" @unless ($lockCarrier) name="shipping_carrier_id" @endunless @disabled($lockCarrier)>
            @unless ($editing)
                <option value="">— Usar default de la empresa —</option>
            @endunless
            @foreach ($carriers as $carrier)
                <option
                    value="{{ $carrier->id }}"
                    data-shipping="{{ $carrier->shipping_cost }}"
                    data-commission-type="{{ $carrier->commission_type }}"
                    data-commission-value="{{ $carrier->commission_value }}"
                    @selected((string) $selectedCarrierId === (string) $carrier->id)
                >
                    {{ $carrier->name }} · {{ $carrier->rateSummary() }}
                </option>
            @endforeach
        </select>
        @if ($lockCarrier)
            <small class="muted">Ya está en Sistrack con esta empresa; no se puede cambiar.</small>
        @endif
    </div>
</div>

<div class="grid-2">
    <div class="field">
        <label for="shipped_at">Fecha</label>
        <input id="shipped_at" type="datetime-local" name="shipped_at" value="{{ old('shipped_at', ($shipment?->shipped_at ?? now())->format('Y-m-d\\TH:i')) }}">
    </div>
    <div class="field">
        <label for="collect_amount">COD a cobrar (USD) *</label>
        <input id="collect_amount" type="number" step="0.01" min="0" name="collect_amount" value="{{ old('collect_amount', $shipment?->collect_amount ?? '9.00') }}" required>
    </div>
</div>

<div class="card" id="preview-box" style="margin:0 0 1rem;padding:1rem;background:#f9fafb">
    <strong>Vista previa liquidación</strong>
    <p class="muted" style="margin:.35rem 0 .75rem">COD − comisión Sistrack − flete − tu comisión = a devolver</p>
    <div class="meta" style="grid-template-columns:repeat(auto-fit,minmax(120px,1fr));margin:0">
        <div class="card" style="margin:0">COD<strong id="pv-cod">—</strong></div>
        <div class="card" style="margin:0">Sistrack<strong id="pv-sistrack">—</strong></div>
        <div class="card" style="margin:0">Flete<strong id="pv-shipping">—</strong></div>
        <div class="card" style="margin:0">Tu comisión<strong id="pv-service">—</strong></div>
        <div class="card" style="margin:0">A devolver<strong id="pv-payable" style="color:var(--signal)">—</strong></div>
    </div>
    @if ($editing)
        <p class="muted" style="margin:.5rem 0 0;font-size:.85rem">Los costos solo se recalculan si cambias el COD, la empresa o la empresa de envío.</p>
    @endif
</div>

<div class="field">
    <label for="description">Descripción / producto *</label>
    <input id="description" type="text" name="description" value="{{ old('description', $shipment?->description) }}" required maxlength="500" placeholder="Ej. Kit facial ×2">
</div>

<hr style="border:0;border-top:1px solid var(--line);margin:1.25rem 0">
<strong style="display:block;margin-bottom:.75rem">Destinatario</strong>

<div class="grid-2">
    <div class="field">
        <label for="recipient_name">Nombre *</label>
        <input id="recipient_name" type="text" name="recipient_name" value="{{ old('recipient_name', $shipment?->recipient_name) }}" required autocomplete="off">
    </div>
    <div class="field">
        <label for="recipient_phone">Teléfono</label>
        <input id="recipient_phone" type="text" name="recipient_phone" value="{{ old('recipient_phone', $shipment?->recipient_phone) }}" autocomplete="off">
    </div>
</div>

@if ($editing && $shipment->recipient_email)
    <input type="hidden" name="recipient_email" value="{{ old('recipient_email', $shipment->recipient_email) }}">
@endif

<div class="field">
    <label for="recipient_address">Dirección *</label>
    <input id="recipient_address" type="text" name="recipient_address" value="{{ old('recipient_address', $shipment?->recipient_address) }}" required autocomplete="off">
</div>

<div class="grid-2">
    <div class="field">
        <label for="department">Departamento *</label>
        <select id="department" name="department" required data-geo-department data-placeholder="Buscar…">
            <option value="">— Selecciona —</option>
            @foreach ($departments as $dept)
                <option value="{{ $dept }}" @selected($selectedDepartment === $dept)>{{ $dept }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="municipality">Municipio *</label>
        <select id="municipality" name="municipality" required data-geo-municipality data-selected="{{ $selectedMunicipality }}" data-placeholder="Buscar…" @disabled(empty($municipalities))>
            <option value="">— Selecciona —</option>
            @foreach ($municipalities as $muni)
                <option value="{{ $muni }}" @selected($selectedMunicipality === $muni)>{{ $muni }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="field">
    <label for="notes">Notas / observaciones Sistrack</label>
    <textarea id="notes" name="notes">{{ old('notes', $shipment?->notes) }}</textarea>
    @if ($editing && $shipment->hasSistrackLabel())
        <small class="muted">Sistrack no permite editar las observaciones de una orden ya creada; este cambio solo queda aquí.</small>
    @endif
</div>

@include('partials.sv-geo-script')

<script>
(() => {
    const clientSelect = document.getElementById('logistics_client_id');
    const carrierSelect = document.getElementById('shipping_carrier_id');
    const collectInput = document.getElementById('collect_amount');

    const fmt = (n) => '$' + (Math.round(n * 100) / 100).toFixed(2);

    const costsFromOption = (opt, collect) => {
        const shipping = parseFloat(opt.dataset.shipping || '0') || 0;
        const type = opt.dataset.commissionType || 'fixed';
        const value = parseFloat(opt.dataset.commissionValue || '0') || 0;
        const commission = type === 'percent' ? collect * (value / 100) : value;
        return { shipping, commission };
    };

    const carrierCosts = (collect) => {
        const opt = carrierSelect.selectedOptions[0];
        if (!opt || !opt.value) {
            const defaultId = clientSelect.selectedOptions[0]?.dataset?.carrier;
            if (defaultId) {
                for (const o of carrierSelect.options) {
                    if (o.value === defaultId) {
                        return costsFromOption(o, collect);
                    }
                }
            }
            return { shipping: 0, commission: 0 };
        }
        return costsFromOption(opt, collect);
    };

    const serviceCommission = (collect) => {
        const opt = clientSelect.selectedOptions[0];
        if (!opt || !opt.value) return 0;
        const type = opt.dataset.commissionType || 'fixed';
        const value = parseFloat(opt.dataset.commissionValue || '0') || 0;
        return type === 'percent' ? collect * (value / 100) : value;
    };

    const refresh = () => {
        const collect = Math.max(0, parseFloat(collectInput.value || '0') || 0);
        const { shipping, commission } = carrierCosts(collect);
        const service = serviceCommission(collect);
        const payable = Math.max(0, collect - commission - shipping - service);
        document.getElementById('pv-cod').textContent = fmt(collect);
        document.getElementById('pv-sistrack').textContent = fmt(commission);
        document.getElementById('pv-shipping').textContent = fmt(shipping);
        document.getElementById('pv-service').textContent = fmt(service);
        document.getElementById('pv-payable').textContent = fmt(payable);
    };

    clientSelect?.addEventListener('change', () => {
        const defaultId = clientSelect.selectedOptions[0]?.dataset?.carrier;
        if (defaultId && !carrierSelect.value && !carrierSelect.disabled) {
            carrierSelect.value = defaultId;
        }
        refresh();
    });
    carrierSelect?.addEventListener('change', refresh);
    collectInput?.addEventListener('input', refresh);
    refresh();
})();
</script>
