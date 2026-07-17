<?php

return [
    'name' => 'Accounting',
    'entry_statuses' => ['draft', 'posted', 'voided'],
    'default_currency' => 'PEN',
    'events' => [
        'queue' => env('ACCOUNTING_EVENTS_QUEUE', 'accounting'),
    ],
];
