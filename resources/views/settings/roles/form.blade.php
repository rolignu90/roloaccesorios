@extends('layouts.app')

@section('title', $role->exists ? 'Editar '.$role->name : 'Nuevo rol')

@section('content')
@php $selected = old('permissions', $role->permissions ?? []); @endphp
<div class="topbar">
    <div><h1>{{ $role->exists ? $role->name : 'Nuevo rol' }}</h1></div>
    <a class="btn btn-secondary" href="{{ route('settings.roles.index') }}">Volver</a>
</div>

<form method="POST" action="{{ $role->exists ? route('settings.roles.update', $role) : route('settings.roles.store') }}">
    @csrf
    @if ($role->exists) @method('PUT') @endif

    <div class="card" style="margin-bottom:1rem">
        <div class="grid-2">
            <div class="field">
                <label for="name">Nombre *</label>
                <input id="name" type="text" name="name" value="{{ old('name', $role->name) }}" maxlength="80" required>
            </div>
            <div class="field">
                <label for="description">Descripción</label>
                <input id="description" type="text" name="description" value="{{ old('description', $role->description) }}" maxlength="255">
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem;margin-bottom:1rem">
        @foreach ($groups as $group => $permissions)
            <div class="card" style="margin:0">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                    <strong>{{ $group }}</strong>
                    <button type="button" class="btn-link" data-toggle-group>Todos</button>
                </div>
                @foreach ($permissions as $key => $label)
                    <label style="display:flex;align-items:flex-start;gap:.5rem;padding:.3rem 0;font-weight:500">
                        <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $selected, true)) style="width:auto;margin-top:.2rem">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        @endforeach
    </div>

    <button class="btn" type="submit">{{ $role->exists ? 'Guardar permisos' : 'Crear rol' }}</button>
</form>

<script>
document.querySelectorAll('[data-toggle-group]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const boxes = btn.closest('.card').querySelectorAll('input[type=checkbox]');
        const allOn = [...boxes].every((b) => b.checked);
        boxes.forEach((b) => { b.checked = !allOn; });
    });
});
</script>
@endsection
