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
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
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

        'coreflow' => (function () {
            $cloudConfig = json_decode(env('LARAVEL_CLOUD_DISK_CONFIG', '[]'), true);
            $diskData = is_array($cloudConfig) ? collect($cloudConfig)->firstWhere('disk', 'coreflow') : null;

            return [
                'driver'                  => 's3',
                'key'                     => $diskData['access_key_id'] ?? env('COREFLOW_ACCESS_KEY_ID', 'b31357dc268f16496ae80224a805178f'),
                'secret'                  => $diskData['access_key_secret'] ?? env('COREFLOW_SECRET_ACCESS_KEY', 'e31c5a65e4be0fa70c9ac052b53ef737db4ca5011bd8607e604acca8aee07aba'),
                'region'                  => $diskData['default_region'] ?? env('COREFLOW_DEFAULT_REGION', 'auto'),
                'bucket'                  => $diskData['bucket'] ?? env('COREFLOW_BUCKET', 'fls-a2e188b3-035f-489a-9e0f-d9a8a61f1a8a'),
                'endpoint'                => $diskData['endpoint'] ?? env('COREFLOW_ENDPOINT', 'https://367be3a2035528943240074d0096e0cd.r2.cloudflarestorage.com'),
                'url'                     => $diskData['url'] ?? env('COREFLOW_URL', 'https://fls-a2e188b3-035f-489a-9e0f-d9a8a61f1a8a.laravel.cloud'),
                'use_path_style_endpoint' => $diskData['use_path_style_endpoint'] ?? false,
                'throw'                   => false,
                'report'                  => false,
            ];
        })(),

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
