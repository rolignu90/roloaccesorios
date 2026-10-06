@extends('layouts.app')

@section('title', 'Mi cuenta')

@section('content')
<div class="topbar">
    <div>
        <h1>Mi cuenta</h1>
        <p class="muted">
            {{ $user->name }} · usuario <strong>{{ $user->username }}</strong> · {{ $user->role?->name ?? 'Sin rol' }}
            @if ($user->seller) · Vendedor: {{ $user->seller->name }} ({{ $user->seller->sale_prefix }})@endif
            @if ($user->stores->isNotEmpty()) · Tiendas: {{ $user->stores->pluck('name')->join(', ') }}@endif
        </p>
    </div>
</div>

<div class="grid-2" style="align-items:start">
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Cambiar contraseña</h2>
        @if ($user->must_change_password)
            <p class="muted">Es tu primer ingreso o un administrador restableció tu contraseña. Elige una nueva para continuar.</p>
        @endif
        <form method="POST" action="{{ route('account.password') }}">
            @csrf
            @method('PUT')
            <div class="field">
                <label for="current_password">Contraseña actual</label>
                <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
            </div>
            <div class="field">
                <label for="password">Nueva contraseña (mínimo 8)</label>
                <input id="password" type="password" name="password" autocomplete="new-password" required>
            </div>
            <div class="field">
                <label for="password_confirmation">Confirmar nueva contraseña</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
            </div>
            <button class="btn" type="submit">Guardar contraseña</button>
        </form>
    </div>

    @unless ($user->must_change_password)
        <div class="card">
            <h2 style="margin-top:0;font-size:1.1rem">PIN de caja</h2>
            <p class="muted">Sirve para cambiar de cajero rápido en el punto de venta sin cerrar sesión. 4 a 6 dígitos, distinto al de los demás.</p>
            <p>Estado: <strong>{{ $user->pos_pin ? 'Configurado' : 'Sin PIN' }}</strong></p>
            <form method="POST" action="{{ route('account.pin') }}">
                @csrf
                @method('PUT')
                <div class="grid-2" style="gap:.6rem">
                    <div class="field" style="margin:0">
                        <label for="pos_pin">Nuevo PIN</label>
                        <input id="pos_pin" type="password" name="pos_pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off">
                    </div>
                    <div class="field" style="margin:0">
                        <label for="pos_pin_confirmation">Confirmar PIN</label>
                        <input id="pos_pin_confirmation" type="password" name="pos_pin_confirmation" inputmode="numeric" maxlength="6" autocomplete="off">
                    </div>
                </div>
                <div class="field" style="margin-top:.6rem">
                    <label for="pin_current_password">Tu contraseña</label>
                    <input id="pin_current_password" type="password" name="current_password" autocomplete="current-password" required>
                </div>
                <button class="btn btn-secondary" type="submit">Guardar PIN</button>
                <small class="muted" style="display:block;margin-top:.4rem">Deja el PIN vacío para eliminarlo.</small>
            </form>
        </div>
    @endunless
</div>
@endsection
