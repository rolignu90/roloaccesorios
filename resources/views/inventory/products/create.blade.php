@extends('layouts.app')

@section('title', isset($sourceProduct) ? 'Duplicar producto' : 'Nuevo producto')

@section('content')
<div class="topbar">
    <div>
        <h1>{{ isset($sourceProduct) ? 'Duplicar producto' : 'Nuevo producto' }}</h1>
        @if (isset($sourceProduct))
            <p class="muted">Basado en {{ $sourceProduct->code }} — {{ $sourceProduct->name }}. El stock no se copia.</p>
        @endif
    </div>
    <a class="btn btn-secondary" href="{{ route('inventory.products.index') }}">Volver</a>
</div>

<div class="card">
    <form method="POST" action="{{ route('inventory.products.store') }}">
        @csrf
        @include('inventory.products._form')
        <button class="btn" type="submit">{{ isset($sourceProduct) ? 'Guardar copia' : 'Guardar' }}</button>
    </form>
</div>
@endsection
