<?php

/*
|--------------------------------------------------------------------------
| Trusted Proxies — FIX [R-01]
|--------------------------------------------------------------------------
| Lista de redes en las que confiamos para leer los headers X-Forwarded-*
| (en particular X-Forwarded-For). Si la conexión a la app llega desde una
| IP que NO está en esta lista, Laravel ignora el XFF y usa la IP directa.
|
| Por defecto se confía en los rangos públicos de Cloudflare (única ruta de
| entrada legítima a producción tras [N-01]). Mantener sincronizado con
| https://www.cloudflare.com/ips/ (revisar trimestralmente).
|
| Override en .env vía:
|   TRUSTED_PROXIES=*                   # todos (NO recomendado en prod)
|   TRUSTED_PROXIES=ip1,ip2,cidr3       # lista explícita
|   TRUSTED_PROXIES=                    # vacío -> usa el default (CF)
*/

return [
    'proxies' => array_values(array_filter(array_map(
        fn (string $v): string => trim($v),
        explode(',', (string) env('TRUSTED_PROXIES', ''))
    ))) ?: [
        // Cloudflare IPv4 (https://www.cloudflare.com/ips-v4)
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        // Cloudflare IPv6 (https://www.cloudflare.com/ips-v6)
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ],
];
