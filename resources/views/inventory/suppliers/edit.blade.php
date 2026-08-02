@extends('layouts.app')

@section('title', 'Editar proveedor')

@section('content')
<div class="topbar">
    <h1>Editar proveedor</h1>
    <a class="btn btn-secondary" href="{{ route('inventory.suppliers.index') }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('inventory.suppliers.update', $supplier) }}">
        @csrf
        @method('PUT')
        @include('inventory.suppliers._form')
        <div class="actions">
            <button class="btn" type="submit">Actualizar</button>
        </div>
    </form>

    <form method="POST" action="{{ route('inventory.suppliers.destroy', $supplier) }}" style="margin-top:1rem" onsubmit="return confirm('¿Eliminar este proveedor?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-danger" type="submit">Eliminar</button>
    </form>
</div>
@endsection
