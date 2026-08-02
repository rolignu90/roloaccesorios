@extends('layouts.app')

@section('title', 'Nuevo gasto')

@section('content')
<div class="topbar">
    <h1>Nuevo gasto</h1>
    <a class="btn btn-secondary" href="{{ route('costs.expenses.index') }}">Volver</a>
</div>
<div class="card">
    <form method="POST" action="{{ route('costs.expenses.store') }}">
        @csrf
        @include('costs.expenses._form')
        <button class="btn" type="submit">Guardar</button>
    </form>
</div>
@endsection
