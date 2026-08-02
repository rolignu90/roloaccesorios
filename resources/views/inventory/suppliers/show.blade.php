@extends('layouts.app')

@section('title', $supplier->name)

@section('content')
<div class="topbar">
    <div>
        <h1>{{ $supplier->name }}</h1>
        <p class="muted">Código {{ $supplier->code }}</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('inventory.suppliers.edit', $supplier) }}">Editar</a>
        <a class="btn" href="{{ route('inventory.suppliers.index') }}">Lista</a>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <p><strong>Contacto:</strong> {{ $supplier->contact_name ?: '—' }}</p>
        <p><strong>Email:</strong> {{ $supplier->email ?: '—' }}</p>
        <p><strong>Teléfono:</strong> {{ $supplier->phone ?: '—' }}</p>
        <p><strong>NIT / Tax ID:</strong> {{ $supplier->tax_id ?: '—' }}</p>
        <p><strong>Dirección:</strong> {{ $supplier->address ?: '—' }}</p>
        <p><strong>Notas:</strong> {{ $supplier->notes ?: '—' }}</p>
    </div>
    <div class="card">
        <h2 style="margin-top:0;font-size:1.1rem">Productos asociados</h2>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>P. compra (USD)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($supplier->products as $product)
                    <tr>
                        <td><a href="{{ route('inventory.products.show', $product) }}">{{ $product->code }} — {{ $product->name }}</a></td>
                        <td>{{ money($product->pivot->purchase_price) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="muted">Sin productos vinculados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
