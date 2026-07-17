<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum InventoryDocumentStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
