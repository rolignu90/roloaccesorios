@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
<div class="topbar">
    <div>
        <h1>Usuarios</h1>
        <p class="muted">Cada persona entra con su usuario. El rol define qué puede ver y hacer; el vendedor vinculado se usa como cajero y vendedor por defecto.</p>
    </div>
    <a class="btn" href="{{ route('settings.users.create') }}">Nuevo usuario</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Vendedor</th>
                <th>Tiendas</th>
                <th>PIN caja</th>
                <th>Último acceso</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $u)
                <tr>
                    <td><strong>{{ $u->name }}</strong></td>
                    <td>{{ $u->username }}</td>
                    <td>{{ $u->role?->name ?? '—' }}</td>
                    <td>{{ $u->seller ? $u->seller->name.' ('.$u->seller->sale_prefix.')' : '—' }}</td>
                    <td>{{ $u->stores->pluck('name')->join(', ') ?: ($u->hasPermission('cash.view_all') ? 'Todas' : '—') }}</td>
                    <td>{{ $u->pos_pin ? 'Sí' : 'No' }}</td>
                    <td>{{ $u->last_login_at?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                    <td>
                        <span class="badge {{ $u->is_active ? 'badge-ok' : 'badge-off' }}">{{ $u->is_active ? 'Activo' : 'Inactivo' }}</span>
                        @if ($u->must_change_password)<span class="badge badge-warn">Debe cambiar contraseña</span>@endif
                    </td>
                    <td class="actions"><a href="{{ route('settings.users.edit', $u) }}">Editar</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Aún no hay usuarios.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
