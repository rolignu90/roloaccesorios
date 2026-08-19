@extends('layouts.app')

@section('title', 'Cuentas por pagar')

@section('content')
<div class="topbar">
    <div>
        <h1>Cuentas por pagar</h1>
        <p class="muted">Compras (entradas de stock) vs pagos a proveedores</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('inventory.payables.create') }}">Registrar pago</a>
        <a class="btn btn-secondary" href="{{ route('inventory.stock.create') }}">Entrada de stock</a>
    </div>
</div>

<div class="meta" style="margin-bottom:1rem">
    <div class="card">Comprado<strong>{{ money($totalPurchased) }}</strong></div>
    <div class="card">Pagado<strong>{{ money($totalPaid) }}</strong></div>
    <div class="card">Adeudado<strong>{{ money($totalBalance) }}</strong></div>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Proveedor</th>
                <th>Comprado</th>
                <th>Pagado</th>
                <th>Saldo</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>
                        <a href="{{ route('inventory.payables.show', $row->supplier) }}">
                            {{ $row->supplier->code }} — {{ $row->supplier->name }}
                        </a>
                    </td>
                    <td>{{ money($row->purchased) }}</td>
                    <td>{{ money($row->paid) }}</td>
                    <td>
                        <strong class="{{ $row->balance > 0.009 ? '' : 'muted' }}">{{ money($row->balance) }}</strong>
                    </td>
                    <td class="actions">
                        @if ($row->balance > 0.009)
                            <form method="POST" action="{{ route('inventory.payables.pay-full', $row->supplier) }}" style="display:inline"
                                  onsubmit="return confirm('¿Pagar completo {{ money($row->balance) }} a {{ $row->supplier->name }}?')">
                                @csrf
                                <button class="btn" type="submit" style="padding:.25rem .6rem;font-size:.85rem">Pagar completo</button>
                            </form>
                            <a href="{{ route('inventory.payables.create', ['supplier_id' => $row->supplier->id]) }}">Pago parcial</a>
                        @endif
                        <a href="{{ route('inventory.payables.show', $row->supplier) }}">Ver</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Sin compras registradas aún.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
