<?php

return [
    'vat_rate' => (float) env('SALES_VAT_RATE', 0.13),
    'default_shipping_amount' => (float) env('SALES_DEFAULT_SHIPPING', 3),
    'payment_methods' => [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
        'credit' => 'Crédito',
        'other' => 'Otro',
    ],
    'store' => [
        'walk_in_customer_name' => 'Consumidor final',
        'payment_methods' => ['cash', 'card', 'transfer'],
    ],
];
