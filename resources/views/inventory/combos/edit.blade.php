@extends('layouts.app')

@section('title', 'Editar combo')

@section('content')
<div class="topbar">
    <div>
        <h1>Editar combo</h1>
        <p class="muted">{{ $combo->code }} — {{ $combo->name }}</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('inventory.combos.show', $combo) }}">Volver</a>
</div>

@if (session('success'))
    <div class="flash" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

<div class="card">
    <form method="POST" action="{{ route('inventory.combos.update', $combo) }}">
        @csrf
        @method('PUT')
        @include('inventory.combos._form', ['combo' => $combo, 'products' => $products])
        <div class="actions" style="margin-top:1rem">
            <button class="btn" type="submit">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection
