<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'registry-local' => [
            'driver' => 'local',
            'root' => env('REGISTRY_STORAGE_PATH', storage_path('app/registry')),
            'visibility' => 'private',
        ],

        'registry-s3' => [
            'driver' => 's3',
            'key' => env('REGISTRY_S3_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('REGISTRY_S3_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('REGISTRY_S3_DEFAULT_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('REGISTRY_S3_BUCKET', env('AWS_BUCKET')),
            'url' => env('REGISTRY_S3_URL', env('AWS_URL')),
            'endpoint' => env('REGISTRY_S3_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('REGISTRY_S3_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'root' => env('REGISTRY_S3_ROOT', '/'),
            'throw' => false,
            'report' => false,
        ],

        'registry-sftp' => [
            'driver' => 'sftp',
            'host' => env('REGISTRY_SFTP_HOST'),
            'username' => env('REGISTRY_SFTP_USERNAME'),
            'password' => env('REGISTRY_SFTP_PASSWORD'),

            'privateKey' => env('REGISTRY_SFTP_PRIVATE_KEY'),
            'passphrase' => env('REGISTRY_SFTP_PASSPHRASE'),

            'port' => env('REGISTRY_SFTP_PORT', 22),
            'root' => env('REGISTRY_SFTP_ROOT'),

            'timeout' => env('REGISTRY_SFTP_TIMEOUT', 10),
            'throw' => false,
            'report' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
