<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Modules\Transport\Data\TransportGuideCommand;
use Modules\Transport\Data\TransportGuideItemData;
use Modules\Transport\Enums\TransportGuideType;
use Modules\Transport\Enums\TransportMode;
use Modules\Transport\Services\TransportGuideService;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $userId, $branchId, $productId, $key] = $argv;
$user = auth()->loginUsingId((int) $userId);
$product = \Modules\Catalog\Entities\Product::query()->findOrFail((int) $productId);

try {
    $guide = app(TransportGuideService::class)->create(new TransportGuideCommand(
        organizationId: (int) $user->organization_id,
        branchId: (int) $branchId,
        idempotencyKey: $key,
        type: TransportGuideType::Sender,
        reasonCode: '01',
        transportMode: TransportMode::Private,
        transferDate: new DateTimeImmutable('+1 hour'),
        origin: ['ubigeo' => '150101', 'address' => 'Av. Partida 100', 'establishment_code' => '0001'],
        destination: ['ubigeo' => '150102', 'address' => 'Av. Llegada 200', 'establishment_code' => '0002'],
        recipient: ['document_type' => '6', 'document_number' => '20987654321', 'name' => 'Cliente F7 SAC'],
        transport: ['vehicle_plate' => 'ABC-123', 'driver_document_number' => '12345678', 'driver_name' => 'Juan', 'driver_license' => 'Q12345678'],
        items: [new TransportGuideItemData((int) $product->id, (string) $product->sku, (string) $product->name, 1)],
        grossWeight: 1,
        actorId: (int) $user->id,
    ));
    fwrite(STDOUT, $guide->id.':'.$guide->number);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception instanceof Illuminate\Validation\ValidationException ? 'validation' : $exception::class.':'.$exception->getMessage());
    exit(2);
}
