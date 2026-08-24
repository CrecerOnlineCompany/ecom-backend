<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => (function () {
        $normalize = function (?string $value): ?string {
            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }

            $parts = parse_url($value);
            if (!is_array($parts)) {
                return null;
            }

            $scheme = $parts['scheme'] ?? null;
            $host = $parts['host'] ?? null;
            if (!$scheme || !$host) {
                return null;
            }

            $port = isset($parts['port']) ? ':' . $parts['port'] : '';
            return strtolower($scheme) . '://' . $host . $port;
        };

        $origins = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://localhost:5174',
            'http://127.0.0.1:5174',
            $normalize(env('FRONTEND_URL')),
            $normalize(env('ECOM_FRONTEND_URL')),
            $normalize(env('APP_URL', 'http://localhost')),
        ];

        return array_values(array_unique(array_filter($origins)));
    })(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization', 'X-Total-Count'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
