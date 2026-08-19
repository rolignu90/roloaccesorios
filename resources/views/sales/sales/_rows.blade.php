@forelse ($sales as $sale)
    @php
        $sistrackExportable = $sale->canSendToSistrack();
        $sistrackBadge = match ($sale->sistrack_status) {
            'sent' => 'badge-ok',
            'failed' => 'badge-off',
            default => $sale->shippingCarrier?->sistrack_enabled ? 'badge-warn' : 'badge-off',
        };
        $disabledReason = $sale->isSistrackSent()
            ? 'Ya enviada a Sistrack'
            : (! $sale->has_shipping
                ? 'Solo confirmadas con envío'
                : (! $sale->shippingCarrier?->sistrack_enabled
                    ? 'Sin Sistrack (empresa de envío sin integración)'
                    : 'No disponible para Sistrack'));
        // Solo seleccionables: pendientes de envío, o atascadas (acciones masivas).
        $selectable = $sistrackExportable || $sale->isStuckInTransit();
    @endphp
    <tr>
        <td>
            @if ($selectable)
                <input
                    type="checkbox"
                    name="sale_ids[]"
                    value="{{ $sale->id }}"
                    @if ($sistrackExportable) data-export-sale data-sistrack-sale @endif
                    @if ($sale->isStuckInTransit()) data-stuck-sale @endif
                    data-sale-number="{{ $sale->number }}"
                    @if ($sistrackExportable) data-send-url="{{ route('sales.sales.send-sistrack.one', $sale) }}" @endif
                >
            @else
                <span class="muted" title="{{ $disabledReason }}">—</span>
            @endif
        </td>
        <td><a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a></td>
        <td>{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
        <td>{{ $sale->customer?->name }}</td>
        <td>{{ $sale->seller?->name ?? '—' }}</td>
        <td>{{ money($sale->taxable_base) }}</td>
        <td>{{ money($sale->vat_amount) }}</td>
        <td>
            @if (! $sale->has_shipping)
                <span class="badge badge-off">Sin envío</span>
            @elseif ((float) $sale->shipping_amount > 0)
                <span class="badge badge-ok">Envío cobrado</span>
                <div class="muted" style="margin-top:.2rem">{{ money($sale->shipping_amount) }}</div>
            @else
                <span class="badge badge-warn">Envío gratis</span>
                <div class="muted" style="margin-top:.2rem">Absorbido</div>
            @endif
        </td>
        <td><strong>{{ money($sale->total) }}</strong></td>
        <td>{{ money($sale->grossMarginWithoutVat()) }}</td>
        <td>{{ money($sale->grossMarginWithVat()) }}</td>
        <td>{{ money($sale->realMarginWithVat()) }}</td>
        <td data-status-cell>
            <span class="badge {{ $sale->statusBadgeClass() }}">
                {{ $sale->statusLabel() }}
            </span>
            @if ($sale->isStuckInTransit())
                <div class="muted" style="margin-top:.2rem;font-size:.75rem;color:#991b1b">
                    Atascada ≥7 días
                </div>
            @endif
        </td>
        <td>
            @if ($sale->has_shipping)
                <span class="badge {{ $sistrackBadge }}" title="{{ $sale->sistrack_last_error }}">
                    {{ $sale->sistrackStatusLabel() }}
                </span>
                @if ($sale->sistrack_shipping_status)
                    <div class="muted" style="margin-top:.2rem;font-size:.75rem">
                        {{ $sale->sistrack_shipping_status }}
                    </div>
                @endif
                @if ($sale->sistrack_status_synced_at)
                    <div class="muted" style="margin-top:.2rem;font-size:.75rem">
                        Sync {{ $sale->sistrack_status_synced_at->format('d/m H:i') }}
                    </div>
                @elseif ($sale->sistrack_last_attempt_at)
                    <div class="muted" style="margin-top:.2rem;font-size:.75rem">
                        {{ $sale->sistrack_last_attempt_at->format('d/m H:i') }}
                    </div>
                @endif
            @else
                <span class="muted">—</span>
            @endif
        </td>
    </tr>
@empty
    @unless ($omitEmpty ?? false)
        <tr data-empty-row><td colspan="14" class="muted">Sin ventas registradas.</td></tr>
    @endunless
@endforelse
