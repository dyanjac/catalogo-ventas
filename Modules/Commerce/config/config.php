<?php

return [
    'name' => 'Commerce',
    'entitlements' => [
        'schema_checks_enabled' => env('ENTITLEMENTS_SCHEMA_CHECKS_ENABLED', true),
        'module_capabilities' => [
            'sales' => 'sales.orders',
            'pos' => 'sales.pos',
            'customers' => 'sales.customers',
            'orders_front' => 'sales.ecommerce',
            'billing' => 'billing.electronic',
            'transport' => 'transport.gre',
            'inventory' => 'inventory.advanced',
            'accounting' => 'accounting.general_ledger',
            'subscriptions' => 'subscriptions.recurring',
        ],
    ],
];
