@extends('layouts.app')

@section('title', 'Editar cliente')

@section('content')
<div class="topbar">
    <h1>Editar cliente</h1>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('sales.customers.show', $customer) }}">Ver ficha</a>
        <a class="btn btn-secondary" href="{{ route('sales.customers.index') }}">Volver</a>
    </div>
</div>
<div class="card">
    <form method="POST" action="{{ route('sales.customers.update', $customer) }}">
        @csrf
        @method('PUT')
        @include('sales.customers._form', ['customer' => $customer, 'codeReadonly' => true])
        <button class="btn" type="submit">Actualizar datos</button>
    </form>
    <form method="POST" action="{{ route('sales.customers.destroy', $customer) }}" style="margin-top:1rem" onsubmit="return confirm('¿Eliminar cliente?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-danger" type="submit">Eliminar</button>
    </form>
</div>

@include('sales.customers._prices', [
    'customer' => $customer,
    'priceGroups' => $priceGroups,
    'products' => $products,
])

@include('partials.sv-geo-script')
@endsection
