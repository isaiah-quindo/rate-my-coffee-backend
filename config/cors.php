<?php

return [
    // Apply CORS to API routes
    'paths' => ['api/*', 'auth/*', 'sanctum/csrf-cookie'],

    // Allow specific origins (no wildcard when using credentials)
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'https://ratemycoffee.ph',
        'https://www.ratemycoffee.ph',
    ],

    // Optionally use patterns if needed
    'allowed_origins_patterns' => [],

    // Allow all methods/headers for the allowed origins
    'allowed_methods' => ['*'],
    'allowed_headers' => ['*'],

    // Expose no special headers by default
    'exposed_headers' => [],

    // Cache preflight response (in seconds)
    'max_age' => 0,

    // Must be true when sending cookies/authorization headers
    'supports_credentials' => true,
];
