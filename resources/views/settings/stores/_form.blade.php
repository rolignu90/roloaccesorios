<div class="grid-2">
    <div class="field">
        <label for="name">Nombre *</label>
        <input id="name" type="text" name="name" value="{{ old('name', $store->name) }}" maxlength="120" required>
    </div>
    <div class="field">
        <label for="phone">Teléfono</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone', $store->phone) }}" maxlength="50">
    </div>
</div>
<div class="field">
    <label for="address">Dirección</label>
    <input id="address" type="text" name="address" value="{{ old('address', $store->address) }}" maxlength="255">
</div>
<div class="grid-2">
    <div class="field">
        <label for="tax_id">NIT / NRC</label>
        <input id="tax_id" type="text" name="tax_id" value="{{ old('tax_id', $store->tax_id) }}" maxlength="50">
    </div>
    <div class="field">
        <label for="ticket_footer">Pie del ticket</label>
        <input id="ticket_footer" type="text" name="ticket_footer" value="{{ old('ticket_footer', $store->ticket_footer) }}" maxlength="255">
    </div>
</div>

<div class="grid-2">
    <div class="field">
        <label for="seller_id">Vendedor por defecto</label>
        <select id="seller_id" name="seller_id">
            <option value="">— Crear vendedor nuevo con el prefijo —</option>
            @foreach ($sellers as $seller)
                <option value="{{ $seller->id }}" @selected((string) old('seller_id', $store->seller_id) === (string) $seller->id)>
                    {{ $seller->name }} ({{ $seller->sale_prefix }})
                </option>
            @endforeach
        </select>
        <small class="muted">El número de ticket usa el prefijo de este vendedor (ej. T-20260928-0001).</small>
    </div>
    <div class="field">
        <label for="ticket_prefix">Prefijo para vendedor nuevo</label>
        <input id="ticket_prefix" type="text" name="ticket_prefix" value="{{ old('ticket_prefix') }}" maxlength="10" placeholder="Ej. T2">
        <small class="muted">Solo si no eliges un vendedor existente.</small>
    </div>
</div>

<div class="field">
    <label style="display:flex;align-items:center;gap:.5rem">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $store->is_active)) style="width:auto">
        Tienda activa
    </label>
</div>
