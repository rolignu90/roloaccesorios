<option value="">— Selecciona producto o combo —</option>
@if (($combos ?? collect())->isNotEmpty())
    <optgroup label="Combos">
        @foreach ($combos as $combo)
            <option
                value="combo:{{ $combo['id'] }}"
                data-kind="combo"
                data-combo-id="{{ $combo['id'] }}"
                data-price="{{ number_format((float) $combo['price'], 2, '.', '') }}"
                data-stock="{{ (int) $combo['stock'] }}"
                data-on-demand="{{ ! empty($combo['on_demand']) ? '1' : '0' }}"
                data-free-shipping="{{ ! empty($combo['free_shipping']) ? '1' : '0' }}"
            >
                {{ $combo['code'] }} — {{ $combo['name'] }}
                ({{ money($combo['price']) }}
                @if (! empty($combo['free_shipping']))
                    · envío gratis
                @endif
                @if (! empty($combo['on_demand']))
                    · on demand
                @else
                    · hasta {{ (int) $combo['stock'] }}
                @endif
                )
            </option>
        @endforeach
    </optgroup>
@endif
<optgroup label="Productos">
    @foreach ($products as $product)
        <option
            value="{{ $product->id }}"
            data-kind="product"
            data-price="{{ number_format($product->effectiveSalePriceWithVat(), 2, '.', '') }}"
            data-wholesale="{{ $product->wholesalePriceWithVat() !== null ? number_format($product->wholesalePriceWithVat(), 2, '.', '') : '' }}"
            data-stock="{{ (int) ($product->stock_on_hand ?? 0) }}"
            data-on-demand="{{ $product->on_demand ? '1' : '0' }}"
            data-free-shipping="{{ $product->free_shipping ? '1' : '0' }}"
            @selected(isset($selectedProductId) && (string) $selectedProductId === (string) $product->id)
        >
            {{ $product->code }} — {{ $product->name }}
            @if ($product->free_shipping)
                · envío gratis
            @endif
            @if ($product->on_demand)
                · on demand
            @endif
            @if ($product->hasActivePromo())
                (promo {{ money($product->effectiveSalePriceWithVat()) }})
            @endif
            @if ($product->on_demand)
                (sin stock requerido)
            @else
                (stock {{ (int) ($product->stock_on_hand ?? 0) }})
            @endif
        </option>
    @endforeach
</optgroup>
