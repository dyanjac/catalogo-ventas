<?php

return [
    'pdf' => [
        'enabled' => true,
        'binary' => env('WKHTML_PDF_BINARY', env('SNAPPY_PDF_BINARY', 'wkhtmltopdf')),
        'timeout' => false,
        'options' => [
            'encoding' => 'UTF-8',
            'enable-local-file-access' => true,
        ],
        'env' => [],
    ],
    'image' => [
        'enabled' => true,
        'binary' => env('WKHTML_IMAGE_BINARY', env('SNAPPY_IMAGE_BINARY', 'wkhtmltoimage')),
        'timeout' => false,
        'options' => [],
        'env' => [],
    ],
];

