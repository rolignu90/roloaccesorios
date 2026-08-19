<?php

return [
    'base_url' => rtrim((string) env('SISTRACK_BASE_URL', 'https://expresselsalvador.sistrack.net'), '/'),
    'email' => env('SISTRACK_EMAIL'),
    'password' => env('SISTRACK_PASSWORD'),
    // Emisor por defecto en Express El Salvador (campo oculto al crear orden).
    'sender_id' => (int) env('SISTRACK_SENDER_ID', 67306),
];
