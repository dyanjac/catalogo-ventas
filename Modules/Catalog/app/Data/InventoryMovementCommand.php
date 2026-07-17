<?php

declare(strict_types=1);

namespace Modules\Catalog\Data;

use Modules\Catalog\Enums\InventoryMovementReason;
use Modules\Catalog\Enums\InventoryMovementType;

final readonly class InventoryMovementCommand
{
    /** @param array<string, mixed>|null $meta */
    public function __construct(
        public int $organizationId,
        public int $productId,
        public int $branchId,
        public ?int $warehouseId,
        public InventoryMovementType $type,
        public InventoryMovementReason $reasonCode,
        public string $idempotencyKey,
        public ?int $quantityDelta = null,
        public ?int $targetStock = null,
        public int $initialStock = 0,
        public float $initialAverageCost = 0,
        public float $unitCost = 0,
        public ?int $performedBy = null,
        public ?string $reason = null,
        public ?string $referenceType = null,
        public ?int $referenceId = null,
        public ?string $referenceCode = null,
        public ?int $reversalOfId = null,
        public ?string $notes = null,
        public ?array $meta = null,
        public bool $requireEmptyLedger = false,
        public int $reservedStockDelta = 0,
        public int $inTransitStockDelta = 0,
    ) {}
}
