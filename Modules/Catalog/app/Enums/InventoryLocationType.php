<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum InventoryLocationType: string
{
    case Warehouse = 'warehouse';
    case Unallocated = 'unallocated';
}
