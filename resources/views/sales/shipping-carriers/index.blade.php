@extends('layouts.app')

@section('title', 'Empresas de envío')

@section('content')
<div class="topbar">
    <div>
        <h1>Empresas de envío</h1>
        <p class="muted">Costo de envío y comisión COD (fijo o % del total c/IVA)</p>
    </div>
    <a class="btn" href="{{ route('sales.shipping-carriers.create') }}">Nueva empresa</a>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('sales.shipping-carriers.index') }}">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre o código">
        <button class="btn" type="submit">Buscar</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Código</th>
                <th>Costo envío</th>
                <th>Comisión COD</th>
                <th>Sistrack</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($carriers as $carrier)
                <tr>
                    <td>{{ $carrier->name }}</td>
                    <td>{{ $carrier->code ?: '—' }}</td>
                    <td>{{ money($carrier->shipping_cost) }}</td>
                    <td>
                        @if ($carrier->commission_type === 'percent')
                            {{ rtrim(rtrim(number_format((float) $carrier->commission_value, 4, '.', ''), '0'), '.') }}% del total c/IVA
                        @else
                            {{ money($carrier->commission_value) }} fijo
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $carrier->supportsSistrack() ? 'badge-ok' : 'badge-off' }}">
                            {{ $carrier->sistrack_enabled ? ($carrier->supportsSistrack() ? 'Sí' : 'Incompleto') : 'No' }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $carrier->is_active ? 'badge-ok' : 'badge-off' }}">
                            {{ $carrier->is_active ? 'Activa' : 'Inactiva' }}
                        </span>
                    </td>
                    <td class="actions">
                        <a href="{{ route('sales.shipping-carriers.edit', $carrier) }}">Editar</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Aún no hay empresas de envío.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $carriers->links() }}</div>
</div>
@endsection
