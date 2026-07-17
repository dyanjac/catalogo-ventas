<?php

declare(strict_types=1);

namespace Modules\Catalog\Enums;

enum InventoryMovementReason: string
{
    case InitialStock = 'initial_stock';
    case PurchaseReceipt = 'purchase_receipt';
    case Sale = 'sale';
    case Transfer = 'transfer';
    case InventoryCount = 'inventory_count';
    case ManualAdjustment = 'manual_adjustment';
    case LegacyBaseline = 'legacy_baseline';
    case Reversal = 'reversal';
    case Dispatch = 'dispatch';
    case Receipt = 'receipt';
    case CustomerReturn = 'customer_return';
    case SupplierReturn = 'supplier_return';
    case Other = 'other';
}
