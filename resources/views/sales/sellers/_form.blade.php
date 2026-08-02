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

<div class="field">
    <input type="hidden" name="is_active" value="0">
    <label>
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $seller?->is_active ?? true))>
        Activo
    </label>
</div>
