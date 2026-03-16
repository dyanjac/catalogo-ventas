<?php

return [
    'default' => env('LDAP_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'hosts' => array_filter([
                env('LDAP_HOST', '127.0.0.1'),
            ]),
            'username' => env('LDAP_USERNAME', ''),
            'password' => env('LDAP_PASSWORD', ''),
            'port' => env('LDAP_PORT', 389),
            'base_dn' => env('LDAP_BASE_DN', ''),
            'timeout' => env('LDAP_TIMEOUT', 5),
            'use_ssl' => env('LDAP_SSL', false),
            'use_tls' => env('LDAP_TLS', false),
            'use_sasl' => env('LDAP_SASL', false),
            'options' => [],
            'sasl_options' => [],
        ],
    ],

    'logging' => [
        'enabled' => env('LDAP_LOGGING', true),
        'channel' => env('LOG_CHANNEL', 'stack'),
        'level' => env('LOG_LEVEL', 'info'),
    ],

    'cache' => [
        'enabled' => env('LDAP_CACHE', false),
        'driver' => env('CACHE_STORE', env('CACHE_DRIVER', 'file')),
    ],
];
