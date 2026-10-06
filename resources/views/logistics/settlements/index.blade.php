@extends('layouts.app')

@section('title', 'Liquidaciones logísticas')

@section('content')
<div class="topbar">
    <div>
        <h1>Liquidaciones a empresas</h1>
        <p class="muted">Pagas el neto: COD − Sistrack − flete − tu comisión</p>
    </div>
    <a class="btn" href="{{ route('logistics.settlements.create') }}">Nueva liquidación</a>
</div>

<div class="card" style="margin-bottom:1rem">
    <form class="search" method="GET" action="{{ route('logistics.settlements.index') }}">
        <select name="client_id">
            <option value="">Todas las empresas</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected((string) request('client_id') === (string) $client->id)>
                    {{ $client->code }} — {{ $client->name }}
                </option>
            @endforeach
        </select>
        <button class="btn" type="submit">Filtrar</button>
    </form>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Empresa</th>
                <th>Período</th>
                <th>Entregados</th>
                <th>COD</th>
                <th>A pagar</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($settlements as $settlement)
                <tr>
                    <td><a href="{{ route('logistics.settlements.show', $settlement) }}">{{ $settlement->number }}</a></td>
                    <td>{{ $settlement->client?->name }}</td>
                    <td>
                        {{ $settlement->periodLabel() }}
                        <div class="muted" style="font-size:.82rem">
                            {{ $settlement->period_from->format('d/m/Y') }} – {{ $settlement->period_to->format('d/m/Y') }}
                        </div>
                    </td>
                    <td>{{ $settlement->shipments_count }}</td>
                    <td>{{ money($settlement->collect_total) }}</td>
                    <td>
                        @if ((float) $settlement->amount_due < 0)
                            <strong style="color:#991b1b">Te deben {{ money(abs((float) $settlement->amount_due)) }}</strong>
                        @else
                            <strong>{{ money($settlement->amount_due) }}</strong>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $settlement->isVoided() ? 'badge-off' : 'badge-ok' }}">
                            {{ $settlement->isVoided() ? 'Anulada' : 'Pagada' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Sin liquidaciones.</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $settlements->links() }}
</div>
@endsection
