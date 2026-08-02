@php($supplier = $supplier ?? null)
<div class="grid-2">
    <div class="field">
        <label for="code">Código *</label>
        <input id="code" type="text" name="code" value="{{ old('code', $supplier?->code) }}" required>
    </div>
    <div class="field">
        <label for="name">Nombre *</label>
        <input id="name" type="text" name="name" value="{{ old('name', $supplier?->name) }}" required>
    </div>
</div>
<div class="grid-2">
    <div class="field">
        <label for="contact_name">Contacto</label>
        <input id="contact_name" type="text" name="contact_name" value="{{ old('contact_name', $supplier?->contact_name) }}">
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $supplier?->email) }}">
    </div>
</div>
<div class="grid-2">
    <div class="field">
        <label for="phone">Teléfono</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone', $supplier?->phone) }}">
    </div>
    <div class="field">
        <label for="tax_id">NIT / Tax ID</label>
        <input id="tax_id" type="text" name="tax_id" value="{{ old('tax_id', $supplier?->tax_id) }}">
    </div>
</div>
<div class="field">
    <label for="address">Dirección</label>
    <textarea id="address" name="address">{{ old('address', $supplier?->address) }}</textarea>
</div>
<div class="field">
    <label for="notes">Notas</label>
    <textarea id="notes" name="notes">{{ old('notes', $supplier?->notes) }}</textarea>
</div>
<div class="field">
    <input type="hidden" name="is_active" value="0">
    <label>
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier?->is_active ?? true))>
        Activo
    </label>
</div>
