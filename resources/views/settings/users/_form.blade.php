@php $selectedStores = collect(old('store_ids', $user->exists ? $user->stores->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all(); @endphp
<div class="grid-2">
    <div class="field">
        <label for="name">Nombre *</label>
        <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" maxlength="120" required>
    </div>
    <div class="field">
        <label for="username">Usuario *</label>
        <input id="username" type="text" name="username" value="{{ old('username', $user->username) }}" maxlength="50" autocapitalize="none" autocomplete="off" required placeholder="ej. marve">
    </div>
</div>
<div class="grid-2">
    <div class="field">
        <label for="role_id">Rol *</label>
        <select id="role_id" name="role_id" required data-no-search>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="seller_id">Vendedor vinculado</label>
        <select id="seller_id" name="seller_id">
            <option value="">— Ninguno —</option>
            @foreach ($sellers as $seller)
                <option value="{{ $seller->id }}" @selected((string) old('seller_id', $user->seller_id) === (string) $seller->id)>{{ $seller->name }} ({{ $seller->sale_prefix }})</option>
            @endforeach
        </select>
        <small class="muted">Sus ventas salen a nombre de este vendedor y será el cajero al abrir caja.</small>
    </div>
</div>

<div class="field">
    <label>Tiendas donde puede vender / operar caja</label>
    <div style="display:flex;flex-wrap:wrap;gap:.5rem 1.25rem">
        @forelse ($stores as $store)
            <label style="display:flex;align-items:center;gap:.4rem;font-weight:500">
                <input type="checkbox" name="store_ids[]" value="{{ $store->id }}" @checked(in_array($store->id, $selectedStores, true)) style="width:auto">
                {{ $store->name }}@unless ($store->is_active) <span class="muted">(inactiva)</span>@endunless
            </label>
        @empty
            <span class="muted">No hay tiendas.</span>
        @endforelse
    </div>
    <small class="muted">Roles con "Ver y operar cajas de todas las tiendas" no necesitan asignación.</small>
</div>

<div class="grid-2">
    <div class="field">
        <label for="email">Correo (opcional)</label>
        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255">
    </div>
    <div class="field">
        <label for="password">{{ $user->exists ? 'Restablecer contraseña' : 'Contraseña inicial' }}</label>
        <input id="password" type="text" name="password" autocomplete="new-password" minlength="8" placeholder="{{ $user->exists ? 'Dejar vacío para no cambiar' : 'Vacío = se genera una temporal' }}">
        <small class="muted">Se le pedirá cambiarla al iniciar sesión.</small>
    </div>
</div>

<div class="field" style="display:flex;gap:1.5rem;flex-wrap:wrap">
    <label style="display:flex;align-items:center;gap:.5rem">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) style="width:auto">
        Usuario activo
    </label>
    @if ($user->exists && $user->pos_pin)
        <label style="display:flex;align-items:center;gap:.5rem">
            <input type="checkbox" name="reset_pin" value="1" style="width:auto">
            Eliminar su PIN de caja
        </label>
    @endif
</div>
