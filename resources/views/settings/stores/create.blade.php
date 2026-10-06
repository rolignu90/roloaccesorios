@extends('layouts.app')

@section('title', 'Nueva tienda')

@section('content')
<div class="topbar">
    <div>
        <h1>Nueva tienda</h1>
    </div>
    <a class="btn btn-secondary" href="{{ route('settings.stores.index') }}">Volver</a>
</div>

<div class="card" style="max-width:820px">
    <form method="POST" action="{{ route('settings.stores.store') }}">
        @csrf
        @include('settings.stores._form')
        <button class="btn" type="submit">Crear tienda</button>
    </form>
</div>
@endsection
