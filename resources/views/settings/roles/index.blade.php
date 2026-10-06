@extends('layouts.app')

@section('title', 'Roles')

@section('content')
@php $labels = collect($groups)->collapse(); @endphp
<div class="topbar">
    <div>
        <h1>Roles y permisos</h1>
        <p class="muted">Marca qué puede hacer cada rol. Los cambios aplican de inmediato a todos sus usuarios.</p>
    </div>
    <a class="btn" href="{{ route('settings.roles.create') }}">Nuevo rol</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1rem">
    @foreach ($roles as $role)
        <div class="card" style="margin:0;display:flex;flex-direction:column;gap:.5rem">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem">
                <strong style="font-size:1.05rem">{{ $role->name }}</strong>
                <span class="muted" style="font-size:.85rem">{{ $role->users_count }} usuario(s)</span>
            </div>
            @if ($role->description)<p class="muted" style="margin:0">{{ $role->description }}</p>@endif
            @if ($role->is_admin)
                <span class="badge badge-ok" style="align-self:flex-start">Acceso total</span>
            @else
                <ul style="margin:0;padding-left:1.1rem;font-size:.88rem">
                    @forelse ($role->permissions ?? [] as $permission)
                        <li>{{ $labels[$permission] ?? $permission }}</li>
                    @empty
                        <li class="muted">Sin permisos</li>
                    @endforelse
                </ul>
                <div class="actions" style="margin-top:auto">
                    <a class="btn btn-secondary" href="{{ route('settings.roles.edit', $role) }}">Editar permisos</a>
                    @if ($role->users_count === 0)
                        <form method="POST" action="{{ route('settings.roles.destroy', $role) }}" onsubmit="return confirm('¿Eliminar este rol?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger" type="submit">Eliminar</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    @endforeach
</div>
@endsection
