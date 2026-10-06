<table>
    <thead>
        <tr><th>Hora</th><th>Tipo</th><th>Motivo</th><th>Usuario</th><th style="text-align:right">Monto</th></tr>
    </thead>
    <tbody>
        @forelse ($movements as $movement)
            <tr>
                <td>{{ $movement->occurred_at?->format('d/m H:i') }}</td>
                <td><span class="badge {{ $movement->type === 'in' ? 'badge-ok' : 'badge-warn' }}">{{ $movement->typeLabel() }}</span></td>
                <td>{{ $movement->reason }}</td>
                <td>{{ $movement->user?->name ?? '—' }}</td>
                <td style="text-align:right">{{ $movement->type === 'in' ? '+' : '−' }}{{ money($movement->amount) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Sin movimientos.</td></tr>
        @endforelse
    </tbody>
</table>
