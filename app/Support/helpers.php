<?php

if (! function_exists('money')) {
    /**
     * Format an amount as US dollars.
     */
    function money(float|int|string|null $amount): string
    {
        $symbol = config('pin.currency_symbol', '$');
        $currency = config('pin.currency', 'USD');

        return $symbol.number_format((float) $amount, 2).' '.$currency;
    }
}

if (! function_exists('vat_rate')) {
    function vat_rate(): float
    {
        return (float) config('sales.vat_rate', 0.13);
    }
}

if (! function_exists('price_with_vat')) {
    function price_with_vat(float|int|string|null $amountWithoutVat): float
    {
        return round((float) $amountWithoutVat * (1 + vat_rate()), 2);
    }
}

if (! function_exists('price_without_vat')) {
    function price_without_vat(float|int|string|null $amountWithVat): float
    {
        $rate = vat_rate();

        if ($rate <= -1) {
            return round((float) $amountWithVat, 2);
        }

        return round((float) $amountWithVat / (1 + $rate), 2);
    }
}

