@extends('layouts.app')

@section('title', 'Editar '.$user->name)

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $user->name }}</h1>
        <p class="muted">Último acceso: {{ $user->last_login_at?->format('d/m/Y H:i') ?? 'nunca' }}</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('settings.users.index') }}">Volver</a>
</div>

<div class="card" style="max-width:860px">
    <form method="POST" action="{{ route('settings.users.update', $user) }}">
        @csrf
        @method('PUT')
        @include('settings.users._form')
        <button class="btn" type="submit">Guardar cambios</button>
    </form>
</div>
@endsection
