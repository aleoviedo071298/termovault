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
];
