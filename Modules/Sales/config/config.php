<?php

return [
    'name' => 'Sales',
    'default_series' => 'POS',
    'default_tax_rate' => 0.18,
    'default_currency' => 'PEN',
    'document_lookup' => [
        'enabled' => env('SALES_DOCUMENT_LOOKUP_ENABLED', true),
        'timeout' => (int) env('SALES_DOCUMENT_LOOKUP_TIMEOUT', 10),
        'verify' => env('SALES_DOCUMENT_LOOKUP_VERIFY', true),
        'ca_bundle' => env('SALES_DOCUMENT_LOOKUP_CA_BUNDLE'),
        'dni' => [
            'url' => env('SALES_DOCUMENT_LOOKUP_DNI_URL', 'https://mpv.essalud.gob.pe/Parametro/BuscarDNI'),
            'payload_key' => env('SALES_DOCUMENT_LOOKUP_DNI_FIELD', 'numeroDNI'),
        ],
        'ruc' => [
            'url' => env('SALES_DOCUMENT_LOOKUP_RUC_URL', 'https://mpv.essalud.gob.pe/Parametro/BuscarRUC'),
            'payload_key' => env('SALES_DOCUMENT_LOOKUP_RUC_FIELD', 'numeroRUC'),
        ],
    ],
];
