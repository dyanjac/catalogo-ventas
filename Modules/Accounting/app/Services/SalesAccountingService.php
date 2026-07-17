<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Enums\EconomicEventStatus;
use Modules\Billing\Models\BillingDocument;
use Modules\Orders\Entities\Order;

/**
 * Adaptador de compatibilidad: los productores existentes ya no escriben asientos
 * directamente; registran eventos económicos idempotentes de FASE 08.
 */
class SalesAccountingService
{
    public function __construct(private readonly EconomicEventService $events) {}

    /** @return array{created:bool,message:string,entry_id:int|null,event_id:int} */
    public function postIssuedSale(Order $order, ?BillingDocument $document = null): array
    {
        $event = $document?->document_type === 'credit_note'
            ? $this->events->recordCreditNote($order, $document, auth()->id())
            : ($document
                ? $this->events->recordInvoice($order, $document, auth()->id())
                : $this->events->recordOrderSale($order, auth()->id()));

        $event->refresh();
        $created = in_array($event->status, [EconomicEventStatus::Processed, EconomicEventStatus::Reversed], true)
            && (bool) $event->processed_entry_id;

        return [
            'created' => $created,
            'message' => $created
                ? 'Evento económico procesado y asiento contable generado.'
                : ($event->error_message ?: 'Evento económico registrado para procesamiento.'),
            'entry_id' => $event->processed_entry_id ? (int) $event->processed_entry_id : null,
            'event_id' => (int) $event->id,
        ];
    }

    /** @return array{created:bool,message:string,entry_id:int|null,event_id:int} */
    public function postPayment(Order $order, ?int $actorId = null): array
    {
        $event = $this->events->recordPayment($order, $actorId);
        $event->refresh();

        return [
            'created' => (bool) $event->processed_entry_id,
            'message' => $event->error_message ?: 'Evento de cobro registrado.',
            'entry_id' => $event->processed_entry_id ? (int) $event->processed_entry_id : null,
            'event_id' => (int) $event->id,
        ];
    }
}
