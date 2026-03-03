<?php

return [
    'name' => env('NOMBRE_EMPRESA', env('nombre_empresa', 'Name Company')),
    'logo' => env('LOGO_EMPRESA', env('logo_empresa', 'img/logo-V&V.png')),
    'email' => env('COMMERCE_EMAIL', env('MAIL_FROM_ADDRESS', '')),
    'address' => env('COMMERCE_ADDRESS', env('APP_ADDRESS', '')),
    'phone' => env('COMMERCE_PHONE', ''),
    'mobile' => env('COMMERCE_MOBILE', ''),
    'tax_id' => env('COMMERCE_TAX_ID', ''),
];
