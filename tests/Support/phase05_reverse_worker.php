<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Modules\Catalog\Services\InventoryDocumentService;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $organizationId, $documentId, $key, $userId] = $argv;
auth()->loginUsingId((int) $userId);

try {
    app(InventoryDocumentService::class)->reverse(
        (int) $documentId,
        $key,
        (int) $userId,
    );
    fwrite(STDOUT, 'ok');
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception instanceof Illuminate\Validation\ValidationException ? 'validation' : $exception::class);
    exit(2);
}
