@extends('layouts.app')

@section('title', 'Nuevo combo')

@section('content')
<div class="topbar">
    <div>
        <h1>Nuevo combo</h1>
        <p class="muted">Define productos, cantidades y el precio final del paquete</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('inventory.combos.index') }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('inventory.combos.store') }}">
        @csrf
        @include('inventory.combos._form')
        <div class="actions" style="margin-top:1rem">
            <button class="btn" type="submit">Guardar combo</button>
        </div>
    </form>
</div>
@endsection
