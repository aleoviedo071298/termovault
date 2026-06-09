<?php

return [
    'headers' => [
        'enabled' => (bool) env('SECURITY_HEADERS_ENABLED', true),

        'values' => [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Content-Security-Policy' => env(
                'SECURITY_CSP',
                "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"
            ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS — orígenes permitidos
    |--------------------------------------------------------------------------
    | Lista de orígenes que pueden hacer requests cross-origin contra la API.
    | El frontend de TermoVault es same-origin (servido por el mismo dominio),
    | por lo que esta lista debería ser **vacía** en producción.
    | Solo se llena en entornos de desarrollo donde Vite corre en otro puerto.
    |
    | Configurar via env `CORS_ALLOWED_ORIGINS` como CSV:
    |   CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
    | En producción dejar la variable vacía o ausente.
    */
    'cors' => [
        'allowed_origins' => array_values(array_filter(array_map(
            fn (string $origin): string => trim($origin),
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
        ))),
    ],
];
