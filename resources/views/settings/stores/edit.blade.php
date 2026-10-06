@extends('layouts.app')

@section('title', 'Editar '.$store->name)

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $store->name }}</h1>
    </div>
    <a class="btn btn-secondary" href="{{ route('settings.stores.index') }}">Volver</a>
</div>

<div class="card" style="max-width:820px">
    <form method="POST" action="{{ route('settings.stores.update', $store) }}">
        @csrf
        @method('PUT')
        @include('settings.stores._form')
        <button class="btn" type="submit">Guardar cambios</button>
    </form>
</div>
@endsection
