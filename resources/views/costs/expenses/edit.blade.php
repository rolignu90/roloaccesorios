@extends('layouts.app')

@section('title', 'Editar gasto')

@section('content')
<div class="topbar">
    <h1>Editar gasto</h1>
    <a class="btn btn-secondary" href="{{ route('costs.expenses.index') }}">Volver</a>
</div>
<div class="card">
    <form method="POST" action="{{ route('costs.expenses.update', $expense) }}">
        @csrf
        @method('PUT')
        @include('costs.expenses._form')
        <button class="btn" type="submit">Actualizar</button>
    </form>
    <form method="POST" action="{{ route('costs.expenses.destroy', $expense) }}" style="margin-top:1rem" onsubmit="return confirm('¿Eliminar gasto?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-danger" type="submit">Eliminar</button>
    </form>
</div>
@endsection
