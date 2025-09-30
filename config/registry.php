<?php

use Illuminate\Support\Str;

$host = env('REGISTRY_HOST', 'localhost:5000');
$insecure = Str::startsWith($host, ['localhost', '127.']);

return [
    // Allow anonymous access to the catalog endpoint
    'anonymous_catalog' => (bool)env('REGISTRY_ANONYMOUS_CATALOG', false),

    'service' => env('REGISTRY_SERVICE', 'container_registry'),

    'host' => $host,
    'endpoint' => env('REGISTRY_ENDPOINT', ($insecure ? 'http://' : 'https://') . $host),

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
            'curve' => env('REGISTRY_JWT_KEY_CURVE', 'prime256v1'),

            'path' => env('REGISTRY_JWT_KEY_PATH', storage_path(env('REGISTRY_JWT_KEY_FILENAME', 'app/certs/registry.pem'))),
            'pass' => env('REGISTRY_JWT_KEY_PASS'),
            'pass_secret' => env('REGISTRY_JWT_KEY_PASS_SECRET', false),
            'cert' => env('REGISTRY_JWT_CERT_PATH', storage_path(env('REGISTRY_JWT_CERT_FILENAME', 'app/certs/registry.crt'))),
            'ttl' => (int)env('REGISTRY_JWT_CERT_TTL', 365),
        ],
        'stable_ca' => [
            'enabled' => (bool)env('REGISTRY_JWT_CA_ENABLED', false),
            'rotate_leaf' => env('REGISTRY_JWT_CA_ROTATE_LEAF_CRON', false),
            'key' => env('REGISTRY_JWT_CA_KEY_PATH', storage_path(env('REGISTRY_JWT_CA_KEY_FILENAME', 'app/certs/registry-ca.pem'))),
            'cert' => env('REGISTRY_JWT_CA_CERT_PATH', storage_path(env('REGISTRY_JWT_CA_CERT_FILENAME', 'app/certs/registry-ca.crt'))),
            'pass' => env('REGISTRY_JWT_CA_KEY_PASS'),
            'pass_secret' => env('REGISTRY_JWT_CA_KEY_PASS_SECRET', false),
            'ttl' => (int)env('REGISTRY_JWT_CA_CERT_TTL', 3650),
        ],
    ],

    'storage' => [
        'enabled' => (bool)env('REGISTRY_STORAGE_ENABLED', false),
        'disk' => 'registry-' . env('REGISTRY_STORAGE_DISK', 'local'),
        // delete the blob when it is not referenced by any manifests after this many days
        'blob_cleanup' => (int)env('REGISTRY_BLOB_CLEANUP', 7),
    ],
];
