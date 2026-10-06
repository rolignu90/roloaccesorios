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
        @if ($customer->returnedSales->isNotEmpty())
            <span class="badge badge-off">{{ $customer->returnedSales->count() }} devolución(es)</span>
        @endif
        <a class="btn btn-secondary" href="{{ route('sales.customers.edit', $customer) }}">Editar datos</a>
        <a class="btn" href="{{ route('sales.sales.create', ['customer_id' => $customer->id, 'customer_mode' => 'existing']) }}">Nueva venta</a>
    </div>
</div>

@if ($customer->returnedSales->isNotEmpty())
    <div class="flash" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;margin-bottom:1rem">
        Este cliente tiene historial de devoluciones. Revisa el riesgo antes de enviar contra entrega.
    </div>
@endif

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
                    <span class="badge {{ $sale->statusBadgeClass() }}">{{ $sale->statusLabel() }}</span>
                    <span class="muted">({{ $sale->sold_at->format('d/m/Y') }})</span>
                </li>
            @empty
                <li class="muted">Sin ventas.</li>
            @endforelse
        </ul>
    </div>
</div>

@if ($customer->returnedSales->isNotEmpty())
    <div class="card" style="margin-top:1rem">
        <h2 style="margin-top:0;font-size:1.1rem">Devoluciones</h2>
        <table>
            <thead>
                <tr>
                    <th>Venta</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Flete</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($customer->returnedSales as $sale)
                    <tr>
                        <td><a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a></td>
                        <td>{{ $sale->sold_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ money($sale->total) }}</td>
                        <td>{{ money($sale->carrier_shipping_cost) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@include('sales.customers._prices', [
    'customer' => $customer,
    'priceGroups' => $priceGroups,
    'products' => $products,
])
@endsection
