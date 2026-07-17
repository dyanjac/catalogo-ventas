<?php

declare(strict_types=1);

namespace Modules\Catalog\Data;

final readonly class InventoryTransferReceiptCommand
{
    /** @param array<int, int> $quantitiesByItemId */
    public function __construct(
        public int $organizationId,
        public int $transferId,
        public string $idempotencyKey,
        public array $quantitiesByItemId,
        public ?int $actorId = null,
        public ?string $notes = null,
    ) {}
}
