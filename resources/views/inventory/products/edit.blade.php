@extends('layouts.app')

@section('title', 'Editar producto')

@section('content')
<div class="topbar">
    <h1>Editar producto</h1>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('inventory.products.duplicate', $product) }}">Duplicar</a>
        <a class="btn btn-secondary" href="{{ route('inventory.products.index') }}">Volver</a>
    </div>
</div>

<div class="card">
    <form method="POST" action="{{ route('inventory.products.update', $product) }}">
        @csrf
        @method('PUT')
        @include('inventory.products._form', ['stockOnHand' => $stockOnHand ?? 0])
        <button class="btn" type="submit">Actualizar</button>
    </form>

    <form method="POST" action="{{ route('inventory.products.destroy', $product) }}" style="margin-top:1rem" onsubmit="return confirm('Si el producto tiene ventas, se desactivará en lugar de borrarse. ¿Continuar?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-danger" type="submit">Eliminar / desactivar</button>
    </form>
</div>
@endsection
