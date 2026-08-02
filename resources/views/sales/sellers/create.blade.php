@extends('layouts.app')

@section('title', 'Nuevo vendedor')

@section('content')
<div class="topbar">
    <h1>Nuevo vendedor</h1>
    <a class="btn btn-secondary" href="{{ route('sales.sellers.index') }}">Volver</a>
</div>
<div class="card">
    <form method="POST" action="{{ route('sales.sellers.store') }}">
        @csrf
        @include('sales.sellers._form', ['nextCode' => \App\Models\Seller::nextCode()])
        <button class="btn" type="submit">Guardar</button>
    </form>
</div>
@endsection
