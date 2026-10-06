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
        $items = $sale->items ?? collect();
        $itemCount = $items->count();
        $qtyTotal = (int) $items->sum('quantity');
        $accordionId = 'sale-items-'.$sale->id;
    @endphp
    <tr data-sale-row="{{ $sale->id }}">
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
        <td>
            <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap">
                <a href="{{ route('sales.sales.show', $sale) }}">{{ $sale->number }}</a>
                @if ($sale->channel && $sale->channel !== \App\Models\Sale::CHANNEL_CRM)
                    <span class="badge {{ $sale->isStoreSale() ? 'badge-ok' : 'badge-warn' }}">{{ $sale->channelLabel() }}</span>
                @endif
                @if ($itemCount > 0)
                    <button
                        type="button"
                        class="btn btn-secondary"
                        style="padding:.15rem .45rem;font-size:.75rem;line-height:1.2"
                        data-sale-items-toggle
                        data-target="{{ $accordionId }}"
                        aria-expanded="false"
                        aria-controls="{{ $accordionId }}"
                        title="Ver productos"
                    >
                        <span data-toggle-label>▸</span>
                        {{ $itemCount }} ítem{{ $itemCount === 1 ? '' : 's' }}
                        @if ($qtyTotal !== $itemCount)
                            · {{ $qtyTotal }} u
                        @endif
                    </button>
                @endif
            </div>
            @if ($itemCount > 0)
                <div class="muted" style="margin-top:.25rem;font-size:.75rem;max-width:16rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $items->map(fn ($i) => ($i->product?->code ?? '?').' ×'.$i->quantity)->implode(', ') }}">
                    {{ $items->take(2)->map(fn ($i) => ($i->product?->code ?? '—').'×'.$i->quantity)->implode(' · ') }}{{ $itemCount > 2 ? '…' : '' }}
                </div>
            @endif
        </td>
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
    @if ($itemCount > 0)
        <tr id="{{ $accordionId }}" data-sale-items-panel="{{ $sale->id }}" hidden>
            <td colspan="14" style="background:#f9fafb;padding:.65rem 1rem 1rem">
                <div style="display:grid;gap:.75rem;grid-template-columns:minmax(220px,1fr) minmax(280px,2fr)">
                    <div style="background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:.75rem">
                        <div style="font-size:.75rem;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);margin-bottom:.4rem">Entrega / cliente</div>
                        <div><strong>{{ $sale->customer?->name ?: '—' }}</strong></div>
                        <div class="muted" style="margin-top:.25rem">Tel: {{ $sale->customer?->phone ?: '—' }}</div>
                        <div style="margin-top:.45rem;line-height:1.4">
                            {{ $sale->customer?->address ?: '—' }}
                        </div>
                        <div class="muted" style="margin-top:.25rem;font-size:.85rem">
                            {{ collect([$sale->customer?->municipality, $sale->customer?->department])->filter()->implode(' · ') ?: 'Sin municipio/departamento' }}
                        </div>
                    </div>
                    <div>
                        <div style="font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);margin-bottom:.45rem">
                            Productos de {{ $sale->number }}
                        </div>
                        <table style="margin:0;background:#fff">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Combo</th>
                                    <th style="text-align:right">Cant.</th>
                                    <th style="text-align:right">P. unit. s/IVA</th>
                                    <th style="text-align:right">Total línea</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <td>
                                            <strong>{{ $item->product?->code ?? '—' }}</strong>
                                            <div class="muted" style="font-size:.85rem">{{ $item->product?->name ?? 'Producto eliminado' }}</div>
                                        </td>
                                        <td>
                                            @if ($item->combo)
                                                {{ $item->combo->code }} — {{ $item->combo->name }}
                                            @else
                                                <span class="muted">—</span>
                                            @endif
                                        </td>
                                        <td style="text-align:right">{{ (int) $item->quantity }}</td>
                                        <td style="text-align:right">{{ money($item->unit_price_without_vat) }}</td>
                                        <td style="text-align:right"><strong>{{ money($item->line_total) }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    @endif
@empty
    @unless ($omitEmpty ?? false)
        <tr data-empty-row><td colspan="14" class="muted">Sin ventas registradas.</td></tr>
    @endunless
@endforelse
