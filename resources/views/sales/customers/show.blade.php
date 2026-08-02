@extends('layouts.app')

@section('title', $customer->name)

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $customer->name }}</h1>
        <p class="muted">
            {{ $customer->code }} · {{ $customer->document_type }}
            @if ($customer->document_type !== 'N/A' && $customer->document_number)
                {{ $customer->document_number }}
            @endif
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('sales.customers.edit', $customer) }}">Editar datos</a>
        <a class="btn" href="{{ route('sales.sales.create', ['customer_id' => $customer->id, 'customer_mode' => 'existing']) }}">Nueva venta</a>
    </div>
</div>
<div class="grid-2">
    <div class="card">
        <p><strong>Email:</strong> {{ $customer->email ?: '—' }}</p>
        <p><strong>Teléfono:</strong> {{ $customer->phone ?: '—' }}</p>
        <p><strong>Dirección:</strong> {{ $customer->address ?: '—' }}</p>
        <p><strong>Municipio:</strong> {{ $customer->municipality ?: '—' }}</p>
        <p><strong>Departamento:</strong> {{ $customer->department ?: '—' }}</p>
        <p><strong>País:</strong> {{ $customer->country ?: '—' }}</p>
        <p><strong>Código postal:</strong> {{ $customer->postal_code ?: '—' }}</p>
        <p><strong>Notas:</strong> {{ $customer->notes ?: '—' }}</p>
    </div>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Últimas ventas</h2>
        <ul>
            @forelse ($customer->sales as $sale)
                <li>
                    <a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a>
                    — {{ money($sale->total) }}
                    <span class="muted">({{ $sale->sold_at->format('d/m/Y') }})</span>
                </li>
            @empty
                <li class="muted">Sin ventas.</li>
            @endforelse
        </ul>
    </div>
</div>

@include('sales.customers._prices', [
    'customer' => $customer,
    'priceGroups' => $priceGroups,
    'products' => $products,
])
@endsection
