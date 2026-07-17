<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum InventoryDocumentType: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case OpeningStock = 'opening_stock';
    case StockAdjustment = 'stock_adjustment';
    case Dispatch = 'dispatch';
    case Receipt = 'receipt';
    case CustomerReturn = 'customer_return';
    case SupplierReturn = 'supplier_return';
    case Compensation = 'compensation';
}
