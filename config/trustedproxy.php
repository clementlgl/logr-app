<?php

$proxies = env('TRUSTED_PROXIES');

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Behind a reverse proxy terminating TLS (Caddy, Traefik...), set
    | TRUSTED_PROXIES to "*" or a comma-separated list of proxy IPs so the
    | X-Forwarded-* headers are honoured and URLs are generated as https.
    | Read by Laravel's TrustProxies middleware on every request.
    |
    */

    'proxies' => in_array($proxies, [null, '', '*'], true) ? ($proxies ?: null) : array_map('trim', explode(',', $proxies)),

];
