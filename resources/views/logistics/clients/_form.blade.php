@php
    $client = $client ?? null;
@endphp

<div class="grid-2">
    <div class="field">
        <label for="code">Código</label>
        <input id="code" type="text" value="{{ $client?->code ?? ($nextCode ?? '') }}" readonly>
        <p class="muted" style="margin:.35rem 0 0">Se asigna automáticamente (LOG-####).</p>
    </div>
    <div class="field">
        <label for="name">Nombre *</label>
        <input id="name" type="text" name="name" value="{{ old('name', $client?->name) }}" required autocomplete="off">
    </div>
</div>

<div class="grid-2">
    <div class="field">
        <label for="phone">Teléfono</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone', $client?->phone) }}" autocomplete="off">
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $client?->email) }}" autocomplete="off">
    </div>
</div>

<div class="card" style="margin:1rem 0;padding:1rem;background:#f9fafb;border:2px solid var(--signal)">
    <strong>Tu comisión por envío</strong>
    <p class="muted" style="margin:.35rem 0 1rem">
        Se descuenta del COD junto con el flete y la comisión COD del courier.
    </p>
    <div class="grid-2">
        <div class="field" style="margin:0">
            <label for="commission_type">Tipo *</label>
            <select id="commission_type" name="commission_type" required>
                @foreach (\App\Models\LogisticsClient::COMMISSION_TYPES as $value => $label)
                    <option value="{{ $value }}" @selected(old('commission_type', $client?->commission_type ?? 'fixed') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin:0">
            <label for="commission_value">Valor *</label>
            <input id="commission_value" type="number" step="0.01" min="0" name="commission_value" value="{{ old('commission_value', $client?->commission_value ?? '1.00') }}" required>
        </div>
    </div>
</div>

<div class="field">
    <label for="default_shipping_carrier_id">Empresa de envío por defecto</label>
    <select id="default_shipping_carrier_id" name="default_shipping_carrier_id">
        <option value="">—</option>
        @foreach ($carriers as $carrier)
            <option value="{{ $carrier->id }}" @selected((string) old('default_shipping_carrier_id', $client?->default_shipping_carrier_id) === (string) $carrier->id)>
                {{ $carrier->name }}
                @if ($carrier->sistrack_enabled) · Sistrack @endif
                · {{ $carrier->rateSummary() }}
            </option>
        @endforeach
    </select>
</div>

<div class="field">
    <label for="notes">Notas</label>
    <textarea id="notes" name="notes">{{ old('notes', $client?->notes) }}</textarea>
</div>

<div class="field">
    <label style="display:flex;align-items:center;gap:.5rem;font-weight:500">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $client?->is_active ?? true))>
        Activo
    </label>
</div>
