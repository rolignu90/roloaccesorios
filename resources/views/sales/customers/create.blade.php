@extends('layouts.app')

@section('title', 'Nuevo cliente')

@section('content')
<div class="topbar">
    <h1>Nuevo cliente</h1>
    <a class="btn btn-secondary" href="{{ route('sales.customers.index') }}">Volver</a>
</div>
<div class="card">
    <form method="POST" action="{{ route('sales.customers.store') }}">
        @csrf
        @include('sales.customers._form', ['nextCode' => \App\Models\Customer::nextCode()])
        <button class="btn" type="submit">Guardar</button>
    </form>
</div>
@include('partials.sv-geo-script')
@endsection
