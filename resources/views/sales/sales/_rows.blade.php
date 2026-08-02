@forelse ($sales as $sale)
    @php
        $exportable = $sale->isConfirmed() && $sale->has_shipping;
    @endphp
    <tr>
        <td>
            @if ($exportable)
                <input type="checkbox" name="sale_ids[]" value="{{ $sale->id }}" data-export-sale>
            @else
                <span class="muted" title="Solo confirmadas con envío">—</span>
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
        <td>
            <span class="badge {{ $sale->isVoided() ? 'badge-off' : 'badge-ok' }}">
                {{ $sale->isVoided() ? 'Anulada' : 'Confirmada' }}
            </span>
        </td>
    </tr>
@empty
    @unless ($omitEmpty ?? false)
        <tr data-empty-row><td colspan="13" class="muted">Sin ventas registradas.</td></tr>
    @endunless
@endforelse
