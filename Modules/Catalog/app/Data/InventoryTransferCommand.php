<?php

declare(strict_types=1);

namespace Modules\Catalog\Data;

final readonly class InventoryTransferCommand
{
    /** @param array<int, InventoryTransferItemData> $items */
    public function __construct(
        public int $organizationId,
        public string $idempotencyKey,
        public int $sourceWarehouseId,
        public int $destinationWarehouseId,
        public array $items,
        public ?int $actorId = null,
        public ?string $notes = null,
    ) {}
}
