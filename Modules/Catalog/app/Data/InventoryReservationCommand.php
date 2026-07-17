<?php

declare(strict_types=1);

namespace Modules\Catalog\Data;

use DateTimeInterface;

final readonly class InventoryReservationCommand
{
    /**
     * @param  array<int, InventoryReservationItemData>  $items
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public int $organizationId,
        public string $idempotencyKey,
        public array $items,
        public ?DateTimeInterface $expiresAt = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?string $sourceCode = null,
        public ?int $actorId = null,
        public array $meta = [],
    ) {}
}
