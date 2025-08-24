<?php

return [
    // Allow anonymous access to the catalog endpoint
    'anonymous_catalog' => (bool)env('REGISTRY_ANONYMOUS_CATALOG', false),

    'service' => env('REGISTRY_SERVICE', 'container_registry'),

    // JWT configuration
    'jwt' => [
        'issuer' => env('REGISTRY_ISSUER', 'registry_token_issuer'),
        'ttl' => (int)env('REGISTRY_TOKEN_TTL', 300),

        // Supported algorithms: RS256, RS384, RS512, ES256, ES384, ES512
        'algorithm' => env('REGISTRY_JWT_ALGORITHM', 'RS256'),

        'key' => [
            // Key type: RSA or EC, must match the algorithm
            'type' => env('REGISTRY_JWT_KEY_TYPE', 'RSA'),
            'size' => (int)env('REGISTRY_JWT_KEY_SIZE', 4096),
            'curve' => env('REGISTRY_JWT_KEY_CURVE', 'P-521'),

            'path' => env('REGISTRY_JWT_KEY_PATH', storage_path(env('REGISTRY_JWT_KEY_FILENAME', 'app/certs/registry.pem'))),
            'pass' => env('REGISTRY_JWT_KEY_PASS'),
            'pass_secret' => env('REGISTRY_JWT_KEY_PASS_SECRET'),
            'cert' => env('REGISTRY_JWT_CERT_PATH', storage_path(env('REGISTRY_JWT_CERT_FILENAME', 'app/certs/registry.crt'))),
            'ttl' => (int)env('REGISTRY_JWT_CERT_TTL', 365),
        ],
        'stable_ca' => [
            'enabled' => (bool)env('REGISTRY_JWT_CA_ENABLED', false),
            'key' => env('REGISTRY_JWT_CA_KEY_PATH', storage_path(env('REGISTRY_JWT_CA_KEY_FILENAME', 'app/certs/registry-ca.pem'))),
            'cert' => env('REGISTRY_JWT_CA_CERT_PATH', storage_path(env('REGISTRY_JWT_CA_CERT_FILENAME', 'app/certs/registry-ca.crt'))),
            'pass' => env('REGISTRY_JWT_CA_KEY_PASS'),
            'pass_secret' => env('REGISTRY_JWT_CA_KEY_PASS_SECRET'),
            'ttl' => (int)env('REGISTRY_JWT_CA_CERT_TTL', 3650),
        ],
    ]
];
