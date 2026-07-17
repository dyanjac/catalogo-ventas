<?php

return [
    'name' => 'Catalog',
    'reservations' => [
        'default_ttl_minutes' => (int) env('INVENTORY_RESERVATION_TTL_MINUTES', 30),
        'maximum_ttl_minutes' => (int) env('INVENTORY_RESERVATION_MAX_TTL_MINUTES', 1440),
        'expire_batch_size' => (int) env('INVENTORY_RESERVATION_EXPIRE_BATCH_SIZE', 500),
        'transaction_attempts' => (int) env('INVENTORY_RESERVATION_TRANSACTION_ATTEMPTS', 5),
    ],
];
