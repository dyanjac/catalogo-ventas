<?php

return [
    'name' => 'Orders',
    'inventory_channels' => [
        'ecommerce' => env('SALES_INVENTORY_ECOMMERCE_MODE', 'legacy'),
        'pos' => env('SALES_INVENTORY_POS_MODE', 'legacy'),
    ],
    'checkout' => [
        'series' => 'PED',
        'currency' => 'PEN',
        'tax_rate' => 0.18,
        'shipping' => 0,
        'discount' => 0,
    ],
];
