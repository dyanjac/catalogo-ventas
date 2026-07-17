<?php

declare(strict_types=1);

namespace Modules\Catalog\Data;

final readonly class InventoryReservationItemData
{
    public function __construct(
        public int $balanceId,
        public int $quantity,
    ) {}
}
