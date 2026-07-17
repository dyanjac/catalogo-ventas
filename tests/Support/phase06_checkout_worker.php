<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Modules\Orders\Services\OrderCheckoutService;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $userId, $productId, $quantity, $key] = $argv;
auth()->loginUsingId((int) $userId);

try {
    $result = app(OrderCheckoutService::class)->checkout([
        'user_id' => (int) $userId,
        'idempotency_key' => $key,
        'name' => 'MySQL Phase 06',
        'address' => 'Lima',
        'city' => 'Lima',
        'phone' => '999999999',
    ], [
        (string) $productId => ['id' => (string) $productId, 'quantity' => (int) $quantity],
    ]);
    fwrite(STDOUT, (string) $result['order']->id);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception instanceof Illuminate\Validation\ValidationException ? 'validation' : $exception::class.':'.$exception->getMessage());
    exit(2);
}
