@extends('layouts.app')

@section('title', 'Editar empresa de envío')

@section('content')
<div class="topbar">
    <h1>Editar empresa de envío</h1>
    <a class="btn btn-secondary" href="{{ route('sales.shipping-carriers.index') }}">Volver</a>
</div>
<div class="card">
    <form method="POST" action="{{ route('sales.shipping-carriers.update', $carrier) }}">
        @csrf
        @method('PUT')
        @include('sales.shipping-carriers._form', ['carrier' => $carrier])
        <button class="btn" type="submit">Actualizar</button>
    </form>
    <form method="POST" action="{{ route('sales.shipping-carriers.destroy', $carrier) }}" style="margin-top:1rem" onsubmit="return confirm('¿Eliminar o desactivar esta empresa?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-danger" type="submit">Eliminar</button>
    </form>
</div>
@endsection
