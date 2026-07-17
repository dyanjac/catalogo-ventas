<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Modules\Catalog\Services\InventoryTransferService;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $organizationId, $transferId, $key, $userId] = $argv;
auth()->loginUsingId((int) $userId);

try {
    app(InventoryTransferService::class)->dispatch(
        (int) $organizationId,
        (int) $transferId,
        $key,
        (int) $userId,
    );
    fwrite(STDOUT, 'ok');
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception instanceof Illuminate\Validation\ValidationException ? 'validation' : $exception::class);
    exit(2);
}
