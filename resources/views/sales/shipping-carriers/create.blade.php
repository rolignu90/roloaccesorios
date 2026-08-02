@extends('layouts.app')

@section('title', 'Nueva empresa de envío')

@section('content')
<div class="topbar">
    <h1>Nueva empresa de envío</h1>
    <a class="btn btn-secondary" href="{{ route('sales.shipping-carriers.index') }}">Volver</a>
</div>
<div class="card">
    <form method="POST" action="{{ route('sales.shipping-carriers.store') }}">
        @csrf
        @include('sales.shipping-carriers._form')
        <button class="btn" type="submit">Guardar</button>
    </form>
</div>
@endsection
