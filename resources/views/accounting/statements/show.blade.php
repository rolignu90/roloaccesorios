@extends('layouts.app')

@section('title', 'Estado de cuenta')

@section('content')
@php
    $party = $statement['party'];
    $summary = $statement['summary'];
    $isSeller = ($party['type'] ?? '') === 'seller';
@endphp
<div class="topbar">
    <div>
        <h1>Estado de cuenta</h1>
        <p class="muted">
            {{ $isSeller ? 'Vendedor' : 'Cliente' }}:
            <strong>{{ $party['code'] ?? '' }} — {{ $party['name'] ?? '' }}</strong>
            @if (!empty($party['phone']))
                · {{ $party['phone'] }}
            @endif
            · {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('accounting.statements', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Otro estado</a>
        <a class="btn btn-secondary" href="{{ route('accounting.dashboard', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Balances</a>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    @include('accounting._periods')
    <form class="search" method="GET" action="{{ $isSeller ? route('accounting.statements.seller', $party['id']) : route('accounting.statements.customer', $party['id']) }}">
        <input type="date" name="from" value="{{ $from->toDateString() }}">
        <input type="date" name="to" value="{{ $to->toDateString() }}">
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="meta" style="margin-bottom:1rem">
    <div class="card"># Ventas<strong>{{ (int) $summary['sales_count'] }}</strong></div>
    <div class="card" style="border-color:#fde68a;background:#fffbeb">Saldo en proceso<strong>{{ money($summary['open_total']) }}</strong><span class="muted">{{ (int) $summary['open_count'] }} pedidos</span></div>
    <div class="card" style="border-color:#a7f3d0;background:#f0fdf4">Entregado<strong>{{ money($summary['delivered_total']) }}</strong><span class="muted">{{ (int) $summary['delivered_count'] }}</span></div>
    <div class="card">Devoluciones<strong>{{ money($summary['returned_total']) }}</strong><span class="muted">{{ (int) $summary['returned_count'] }}</span></div>
    <div class="card">Actividad total<strong>{{ money($summary['activity_total']) }}</strong><span class="muted">suma del período</span></div>
</div>

<div class="card" style="overflow-x:auto">
    <h2 style="margin-top:0;font-size:1.1rem">Movimientos</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Venta</th>
                <th>Estado</th>
                @if ($isSeller)
                    <th>Cliente</th>
                @else
                    <th>Vendedor</th>
                @endif
                <th>Courier</th>
                <th>Pago</th>
                <th style="text-align:right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($statement['lines'] as $line)
                <tr>
                    <td>{{ $line->sold_at?->format('d/m/Y H:i') }}</td>
                    <td><a href="{{ route('sales.sales.show', $line->id) }}">{{ $line->number }}</a></td>
                    <td><span class="badge {{ $line->status_badge }}">{{ $line->status_label }}</span></td>
                    <td>{{ $isSeller ? ($line->customer ?: '—') : ($line->seller ?: '—') }}</td>
                    <td>{{ $line->carrier ?: '—' }}</td>
                    <td>{{ $line->payment_method ?: '—' }}</td>
                    <td style="text-align:right"><strong>{{ money($line->total) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Sin ventas en el período.</td></tr>
            @endforelse
        </tbody>
        @if ($statement['lines']->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="6"><strong>Total actividad</strong></td>
                    <td style="text-align:right"><strong>{{ money($summary['activity_total']) }}</strong></td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
@endsection
