<?php

return [
    'brand_name' => env('MARKETING_BRAND_NAME', 'MetisHub ERP'),
    'description' => 'Gestiona ventas, inventario, clientes y facturación electrónica desde una plataforma ERP conectada.',
    'analytics' => [
        'enabled' => (bool) env('MARKETING_ANALYTICS_ENABLED', false),
        'domain' => env('MARKETING_ANALYTICS_DOMAIN'),
        'script_url' => env('MARKETING_ANALYTICS_SCRIPT_URL', 'https://plausible.io/js/script.js'),
    ],
];
