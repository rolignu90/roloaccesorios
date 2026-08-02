@extends('layouts.app')

@section('title', 'Nuevo proveedor')

@section('content')
<div class="topbar">
    <h1>Nuevo proveedor</h1>
    <a class="btn btn-secondary" href="{{ route('inventory.suppliers.index') }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('inventory.suppliers.store') }}">
        @csrf
        @include('inventory.suppliers._form')
        <button class="btn" type="submit">Guardar</button>
    </form>
</div>
@endsection
