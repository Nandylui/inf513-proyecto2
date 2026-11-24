<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    // React en desarrollo (Vite, puerto 5173) y la URL configurada para producción.
    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        ['http://localhost:5173', 'http://127.0.0.1:5173', 'http://localhost:4200'],
        array_map('trim', explode(',', (string) env('FRONTEND_URL', '')))
    )))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 3600,
    'supports_credentials' => false,
];
