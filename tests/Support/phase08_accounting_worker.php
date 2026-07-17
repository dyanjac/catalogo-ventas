<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Modules\Accounting\Enums\EconomicEventType;
use Modules\Accounting\Services\EconomicEventService;
use Modules\Orders\Entities\Order;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $organizationId, $orderId, $productId, $key] = $argv;

try {
    $events = app(EconomicEventService::class);
    $event = $events->record(
        (int) $organizationId,
        EconomicEventType::InvoiceIssued,
        $key,
        Order::class,
        (int) $orderId,
        'MYSQL-'.$orderId,
        [
            'order_id' => (int) $orderId,
            'document_type' => 'order',
            'total' => '118.00',
            'items' => [['product_id' => (int) $productId, 'line_total' => '118.00', 'tax_amount' => '18.00']],
        ],
    );
    $entry = $events->process((int) $organizationId, (int) $event->id) ?? $event->fresh()->entry;
    fwrite(STDOUT, $event->id.':'.($entry?->id ?? 0));
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.':'.$exception->getMessage());
    exit(2);
}
