<?php

declare(strict_types=1);

namespace Modules\Transport\Data;

use Modules\Transport\Enums\TransportGuideType;
use Modules\Transport\Enums\TransportMode;

final readonly class TransportGuideCommand
{
    /**
     * @param  array<string, mixed>  $origin
     * @param  array<string, mixed>  $destination
     * @param  array<string, mixed>  $recipient
     * @param  array<string, mixed>  $transport
     * @param  array<int, TransportGuideItemData>  $items
     */
    public function __construct(
        public int $organizationId,
        public int $branchId,
        public string $idempotencyKey,
        public TransportGuideType $type,
        public string $reasonCode,
        public TransportMode $transportMode,
        public \DateTimeImmutable $transferDate,
        public array $origin,
        public array $destination,
        public array $recipient,
        public array $transport,
        public array $items,
        public float $grossWeight,
        public string $weightUnit = 'KGM',
        public ?int $packageCount = null,
        public ?int $inventoryDocumentId = null,
        public ?int $inventoryTransferId = null,
        public ?int $billingDocumentId = null,
        public ?int $relatedGuideId = null,
        public ?array $externalSender = null,
        public ?string $exceptionJustification = null,
        public ?int $actorId = null,
        public ?string $notes = null,
    ) {}
}
