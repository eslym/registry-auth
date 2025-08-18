<?php

return [
    'anonymous_catalog' => (bool)env('REGISTRY_ANONYMOUS_CATALOG', false),
    'service' => env('REGISTRY_SERVICE', 'container_registry'),
    'issuer' => env('REGISTRY_ISSUER', 'registry_token_issuer'),
    'ttl' => (int)env('REGISTRY_TOKEN_TTL', 300),
    'key' => [
        'path' => env('REGISTRY_JWT_KEY_PATH', storage_path(env('REGISTRY_JWT_KEY_FILENAME', 'registry.pem'))),
        'pass' => env('REGISTRY_JWT_KEY_PASS'),
        'cert' => env('REGISTRY_JWT_CERT_PATH', storage_path(env('REGISTRY_JWT_CERT_FILENAME', 'registry_ca.crt'))),
    ],
];
