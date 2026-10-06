@extends('layouts.app')

@section('title', 'Elegir tienda')

@section('content')
<div class="topbar">
    <div>
        <h1>¿En qué tienda está este equipo?</h1>
        <p class="muted">Se recuerda en este navegador. Puedes cambiarla cuando quieras desde el punto de venta.</p>
    </div>
    <a class="btn btn-secondary" href="{{ route('settings.stores.index') }}">Administrar tiendas</a>
</div>

@if ($stores->isEmpty())
    <div class="card">
        <p style="margin-top:0">No hay tiendas activas.</p>
        <a class="btn" href="{{ route('settings.stores.create') }}">Crear tienda</a>
    </div>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem">
        @foreach ($stores as $store)
            <form method="POST" action="{{ route('store.select.store') }}" class="card" style="margin:0;display:flex;flex-direction:column;gap:.5rem">
                @csrf
                <input type="hidden" name="store_id" value="{{ $store->id }}">
                <input type="hidden" name="redirect" value="{{ $redirect }}">
                <strong style="font-size:1.1rem">{{ $store->name }}</strong>
                <span class="muted">{{ $store->address ?: 'Sin dirección' }}</span>
                <span class="muted" style="font-size:.85rem">Tickets {{ $store->seller?->sale_prefix ?? '—' }}</span>
                <button class="btn {{ $current?->id === $store->id ? 'btn-secondary' : '' }}" type="submit" style="margin-top:auto">
                    {{ $current?->id === $store->id ? 'Tienda actual' : 'Usar esta tienda' }}
                </button>
            </form>
        @endforeach
    </div>
@endif
@endsection
