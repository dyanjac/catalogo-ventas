<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Modules\Orders\Entities\Order;
use Modules\Orders\Services\OrderInventoryLifecycleService;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $userId, $orderId] = $argv;
auth()->loginUsingId((int) $userId);

try {
    $order = Order::query()->findOrFail((int) $orderId);
    app(OrderInventoryLifecycleService::class)->confirmDispatch($order, (int) $userId);
    fwrite(STDOUT, 'ok');
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception instanceof Illuminate\Validation\ValidationException ? 'validation' : $exception::class.':'.$exception->getMessage());
    exit(2);
}
