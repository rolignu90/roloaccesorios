@extends('layouts.app')

@section('title', 'Liquidaciones de vendedores')

@section('content')
<div class="topbar">
    <div>
        <h1>Liquidaciones de vendedores</h1>
        <p class="muted">Semanal o mensual · externos (margen) e internos (salario + comisión)</p>
    </div>
    <a class="btn" href="{{ route('sales.seller-settlements.create') }}">Nueva liquidación</a>
</div>

@if (session('success'))
    <div class="flash" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('sales.seller-settlements.index') }}" style="flex-wrap:wrap">
        <select name="seller_id">
            <option value="">Todos los vendedores</option>
            @foreach ($sellers as $seller)
                <option value="{{ $seller->id }}" @selected((string) request('seller_id') === (string) $seller->id)>
                    {{ $seller->code }} — {{ $seller->name }}
                </option>
            @endforeach
        </select>
        <select name="status">
            <option value="">Todos los estados</option>
            <option value="paid" @selected(request('status') === 'paid')>Pagadas</option>
            <option value="voided" @selected(request('status') === 'voided')>Anuladas</option>
        </select>
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Vendedor</th>
                <th>Tipo</th>
                <th>Período</th>
                <th>Ventas</th>
                <th>A pagar</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($settlements as $row)
                <tr>
                    <td><a href="{{ route('sales.seller-settlements.show', $row) }}">{{ $row->number }}</a></td>
                    <td>{{ $row->seller?->code }} — {{ $row->seller?->name }}</td>
                    <td>{{ $row->sellerTypeLabel() }}</td>
                    <td>
                        {{ $row->periodLabel() }}<br>
                        <span class="muted">{{ $row->period_from->format('d/m/Y') }} – {{ $row->period_to->format('d/m/Y') }}</span>
                    </td>
                    <td>{{ $row->sales_count }} / {{ $row->returns_count }} dev.</td>
                    <td><strong>{{ money($row->amount_due) }}</strong></td>
                    <td>
                        <span class="badge {{ $row->isVoided() ? 'badge-off' : 'badge-ok' }}">
                            {{ $row->isVoided() ? 'Anulada' : 'Pagada' }}
                        </span>
                    </td>
                    <td><a href="{{ route('sales.seller-settlements.show', $row) }}">Ver</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Aún no hay liquidaciones.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $settlements->links() }}</div>
</div>
@endsection
