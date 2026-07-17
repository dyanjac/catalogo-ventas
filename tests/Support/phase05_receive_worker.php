<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Modules\Catalog\Data\InventoryTransferReceiptCommand;
use Modules\Catalog\Services\InventoryTransferService;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $organizationId, $transferId, $itemId, $quantity, $key, $userId] = $argv;
auth()->loginUsingId((int) $userId);

try {
    app(InventoryTransferService::class)->receive(new InventoryTransferReceiptCommand(
        organizationId: (int) $organizationId,
        transferId: (int) $transferId,
        idempotencyKey: $key,
        quantitiesByItemId: [(int) $itemId => (int) $quantity],
        actorId: (int) $userId,
    ));
    fwrite(STDOUT, 'ok');
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception instanceof Illuminate\Validation\ValidationException ? 'validation' : $exception::class);
    exit(2);
}
