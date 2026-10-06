@extends('layouts.app')

@section('title', 'Editar vendedor')

@section('content')
<div class="topbar">
    <h1>Editar vendedor</h1>
    <a class="btn btn-secondary" href="{{ route('sales.sellers.index') }}">Volver</a>
</div>
<div class="card">
    <form method="POST" action="{{ route('sales.sellers.update', $seller) }}">
        @csrf
        @method('PUT')
        @include('sales.sellers._form', ['seller' => $seller, 'codeReadonly' => true, 'customers' => $customers ?? collect()])
        <button class="btn" type="submit">Actualizar</button>
    </form>
    <form method="POST" action="{{ route('sales.sellers.destroy', $seller) }}" style="margin-top:1rem" onsubmit="return confirm('Si tiene ventas se desactivará. ¿Continuar?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-danger" type="submit">Eliminar / desactivar</button>
    </form>
</div>
@endsection
