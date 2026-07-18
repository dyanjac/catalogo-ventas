<?php

return [
    'reconciliation' => [
        'schedule' => env('OPERATIONS_RECONCILIATION_SCHEDULE', '*/15 * * * *'),
        'queue' => env('OPERATIONS_QUEUE', 'operations'),
        'stale_after_minutes' => (int) env('OPERATIONS_STALE_AFTER_MINUTES', 15),
        'freshness_minutes' => (int) env('OPERATIONS_FRESHNESS_MINUTES', 30),
    ],
    'recovery' => [
        'stale_processing_minutes' => (int) env('OPERATIONS_STALE_EVENT_MINUTES', 15),
    ],
    'readiness' => [
        'max_reconciliation_age_minutes' => (int) env('OPERATIONS_READY_MAX_RECONCILIATION_AGE', 30),
        'require_recent_reconciliation' => (bool) env('OPERATIONS_READY_REQUIRE_RECONCILIATION', false),
    ],
    'queue' => [
        'required_retry_after' => (int) env('OPERATIONS_REQUIRED_RETRY_AFTER', 960),
    ],
];
