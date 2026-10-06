@extends('layouts.app')

@section('title', 'Nuevo usuario')

@section('content')
<div class="topbar">
    <div><h1>Nuevo usuario</h1></div>
    <a class="btn btn-secondary" href="{{ route('settings.users.index') }}">Volver</a>
</div>

<div class="card" style="max-width:860px">
    <form method="POST" action="{{ route('settings.users.store') }}">
        @csrf
        @include('settings.users._form')
        <button class="btn" type="submit">Crear usuario</button>
    </form>
</div>
@endsection
