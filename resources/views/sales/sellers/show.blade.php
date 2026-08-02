@extends('layouts.app')

@section('title', $seller->name)

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $seller->name }}</h1>
        <p class="muted">
            {{ $seller->code }} · Prefijo {{ $seller->sale_prefix ?: 'V-' }} ·
            <span class="badge {{ $seller->is_active ? 'badge-ok' : 'badge-off' }}">
                {{ $seller->is_active ? 'Activo' : 'Inactivo' }}
            </span>
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('sales.sellers.edit', $seller) }}">Editar</a>
        <a class="btn" href="{{ route('sales.sales.create', ['seller_id' => $seller->id]) }}">Nueva venta</a>
    </div>
</div>
<div class="grid-2">
    <div class="card">
        <p><strong>Prefijo de venta:</strong> {{ $seller->sale_prefix ?: 'V-' }}</p>
        <p><strong>Teléfono:</strong> {{ $seller->phone ?: '—' }}</p>
        <p><strong>Email:</strong> {{ $seller->email ?: '—' }}</p>
        <p><strong>Notas:</strong> {{ $seller->notes ?: '—' }}</p>
    </div>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Últimas ventas</h2>
        <ul>
            @forelse ($seller->sales as $sale)
                <li>
                    <a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a>
                    — {{ $sale->customer?->name }}
                    — {{ money($sale->total) }}
                    <span class="muted">({{ $sale->sold_at->format('d/m/Y') }})</span>
                </li>
            @empty
                <li class="muted">Sin ventas.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
