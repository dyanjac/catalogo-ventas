<?php

declare(strict_types=1);

namespace Modules\Catalog\Data;

final readonly class InventoryTransferItemData
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}
}
