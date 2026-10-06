<?php

return [
    /*
    |--------------------------------------------------------------------------
    | E-commerce API (server-to-server)
    |--------------------------------------------------------------------------
    |
    | Token Bearer compartido. La tienda lo usa solo desde su backend.
    | Seller: id explícito o código (p. ej. WEB). Debe existir y estar activo.
    |
    */
    'api_token' => env('ECOMMERCE_API_TOKEN'),

    'seller_id' => env('ECOMMERCE_SELLER_ID') ? (int) env('ECOMMERCE_SELLER_ID') : null,

    'seller_code' => env('ECOMMERCE_SELLER_CODE', 'WEB'),

    /** Si false, rechaza pedidos sin stock suficiente (no on-demand). */
    'allow_on_demand' => filter_var(env('ECOMMERCE_ALLOW_ON_DEMAND', true), FILTER_VALIDATE_BOOL),

    'default_shipping_amount' => (float) env(
        'ECOMMERCE_DEFAULT_SHIPPING',
        env('SALES_DEFAULT_SHIPPING', 3)
    ),
];
