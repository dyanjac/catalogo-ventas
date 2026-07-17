<?php

namespace Modules\Accounting\Enums;

enum EconomicEventType: string
{
    case InvoiceIssued = 'invoice_issued';
    case InventoryDispatched = 'inventory_dispatched';
    case PaymentReceived = 'payment_received';
    case CreditNoteIssued = 'credit_note_issued';
    case InventoryReturned = 'inventory_returned';
    case EntryReversal = 'entry_reversal';

    public function label(): string
    {
        return match ($this) {
            self::InvoiceIssued => 'Comprobante emitido',
            self::InventoryDispatched => 'Costo de venta',
            self::PaymentReceived => 'Cobro recibido',
            self::CreditNoteIssued => 'Nota de crédito emitida',
            self::InventoryReturned => 'Devolución física',
            self::EntryReversal => 'Reversión contable',
        };
    }
}
