<?php

return [
    'name' => 'Subscriptions',
    'timezone' => 'UTC',
    'queue' => env('SUBSCRIPTIONS_QUEUE', 'subscriptions'),
    'claim_lease_minutes' => (int) env('SUBSCRIPTIONS_CLAIM_LEASE_MINUTES', 15),
];
