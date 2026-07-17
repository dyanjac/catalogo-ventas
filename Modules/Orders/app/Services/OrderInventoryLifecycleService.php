<?php

declare(strict_types=1);

namespace Modules\Orders\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Services\EconomicEventService;
use Modules\Catalog\Data\InventoryReservationCommand;
use Modules\Catalog\Data\InventoryReservationItemData;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryReservation;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Enums\InventoryDocumentStatus;
use Modules\Catalog\Enums\InventoryReservationStatus;
use Modules\Catalog\Services\InventoryDocumentService;
use Modules\Catalog\Services\InventoryReservationService;
use Modules\Orders\Entities\Order;
use Modules\Orders\Enums\OrderWarehouseStatus;

class OrderInventoryLifecycleService
{
    public function __construct(
        private readonly InventoryReservationService $reservations,
        private readonly InventoryDocumentService $documents,
        private readonly SalesInventoryChannelRolloutService $rollouts,
        private readonly EconomicEventService $economicEvents,
    ) {}

    public function reserve(Order $order, ?int $actorId = null): Order
    {
        return DB::transaction(function () use ($order, $actorId): Order {
            $locked = Order::query()
                ->where('organization_id', $order->organization_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! $this->rollouts->isActive((int) $locked->organization_id, (string) $locked->sales_channel)) {
                throw ValidationException::withMessages(['channel' => 'El flujo integrado de inventario no esta activo para este canal.']);
            }
            if ($locked->inventory_reservation_id) {
                $currentReservation = InventoryReservation::query()
                    ->where('organization_id', $locked->organization_id)
                    ->findOrFail($locked->inventory_reservation_id);
                if ($currentReservation->status === InventoryReservationStatus::Active) {
                    return $locked->load(['items', 'inventoryReservation.items']);
                }
                if ($currentReservation->status !== InventoryReservationStatus::Expired
                    || $locked->warehouse_status !== OrderWarehouseStatus::ReservationExpired) {
                    throw ValidationException::withMessages(['reservation' => 'La reserva del pedido esta en un estado terminal y no puede reemplazarse.']);
                }
            }

            $warehouse = InventoryWarehouse::query()
                ->where('organization_id', $locked->organization_id)
                ->where('branch_id', $locked->branch_id)
                ->where('is_default', true)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            if (! $warehouse) {
                throw ValidationException::withMessages(['warehouse' => 'La sucursal no tiene un almacen predeterminado activo.']);
            }

            $items = $locked->items()->with('product')->orderBy('id')->lockForUpdate()->get();
            $inventoryItems = $items->filter(fn ($item) => $item->product?->tracksInventory());
            if ($inventoryItems->isEmpty()) {
                $locked->forceFill([
                    'warehouse_id' => $warehouse->id,
                    'warehouse_status' => OrderWarehouseStatus::NotRequired->value,
                ])->save();

                return $locked->fresh(['items']);
            }

            $productIds = $inventoryItems->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $balances = InventoryBalance::query()
                ->where('organization_id', $locked->organization_id)
                ->where('branch_id', $locked->branch_id)
                ->where('warehouse_id', $warehouse->id)
                ->where('is_active', true)
                ->whereIn('product_id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');
            if ($balances->count() !== $productIds->count()) {
                throw ValidationException::withMessages(['items' => 'Todos los productos fisicos requieren saldo activo en el almacen predeterminado.']);
            }

            $quantities = $inventoryItems->groupBy('product_id')->map(fn ($lines) => (int) $lines->sum('quantity'));
            $reservationVersion = (int) $locked->reservation_version + 1;
            $reservation = $this->reservations->reserve(new InventoryReservationCommand(
                organizationId: (int) $locked->organization_id,
                idempotencyKey: "sales-order:{$locked->id}:reserve:{$reservationVersion}",
                items: $quantities->map(fn (int $quantity, int|string $productId) => new InventoryReservationItemData(
                    (int) $balances->get((int) $productId)->id,
                    $quantity,
                ))->values()->all(),
                sourceType: Order::class,
                sourceId: (int) $locked->id,
                sourceCode: $this->orderCode($locked),
                actorId: $actorId,
                meta: ['channel' => $locked->sales_channel],
            ));
            $reservationItems = $reservation->items->keyBy('product_id');

            foreach ($inventoryItems as $item) {
                $balance = $balances->get((int) $item->product_id);
                $reservationItem = $reservationItems->get((int) $item->product_id);
                $item->forceFill([
                    'organization_id' => $locked->organization_id,
                    'warehouse_id' => $warehouse->id,
                    'inventory_balance_id' => $balance->id,
                    'inventory_reservation_item_id' => $reservationItem?->id,
                    'reserved_quantity' => $item->quantity,
                ])->save();
            }

            $locked->forceFill([
                'warehouse_id' => $warehouse->id,
                'inventory_reservation_id' => $reservation->id,
                'warehouse_status' => OrderWarehouseStatus::Reserved->value,
                'reservation_version' => $reservationVersion,
                'reserved_at' => now(),
            ])->save();

            return $locked->fresh(['items', 'inventoryReservation.items', 'warehouse']);
        });
    }

    public function requestDispatch(Order $order, ?int $actorId = null): InventoryDocument
    {
        return DB::transaction(function () use ($order, $actorId): InventoryDocument {
            $locked = Order::query()->where('organization_id', $order->organization_id)->lockForUpdate()->findOrFail($order->id);
            if ($locked->dispatch_document_id) {
                return InventoryDocument::query()->where('organization_id', $locked->organization_id)->findOrFail($locked->dispatch_document_id);
            }
            if (! $locked->inventory_reservation_id || $locked->warehouse_status !== OrderWarehouseStatus::Reserved) {
                throw ValidationException::withMessages(['order' => 'El pedido debe tener una reserva activa antes de solicitar despacho.']);
            }

            $reservation = InventoryReservation::query()
                ->where('organization_id', $locked->organization_id)
                ->with('items')
                ->findOrFail($locked->inventory_reservation_id);
            if ($reservation->status !== InventoryReservationStatus::Active) {
                throw ValidationException::withMessages(['reservation' => 'La reserva del pedido ya no esta activa.']);
            }

            $document = $this->documents->createDraft([
                'organization_id' => $locked->organization_id,
                'document_type' => 'dispatch',
                'branch_id' => $locked->branch_id,
                'warehouse_id' => $locked->warehouse_id,
                'reservation_id' => $reservation->id,
                'idempotency_key' => "sales-order:{$locked->id}:dispatch:{$locked->reservation_version}",
                'external_reference' => $this->orderCode($locked),
                'reason' => 'sales_dispatch',
                'created_by' => $actorId,
                'items' => $reservation->items->groupBy('product_id')->map(fn ($items, $productId) => [
                    'product_id' => (int) $productId,
                    'quantity' => (int) $items->sum('quantity'),
                ])->values()->all(),
                'meta' => ['order_id' => $locked->id, 'channel' => $locked->sales_channel],
            ]);

            $locked->forceFill([
                'dispatch_document_id' => $document->id,
                'warehouse_status' => OrderWarehouseStatus::DispatchRequested->value,
                'dispatch_requested_at' => now(),
            ])->save();

            return $document;
        });
    }

    public function confirmDispatch(Order $order, ?int $actorId = null): Order
    {
        $document = $this->requestDispatch($order, $actorId);

        $confirmedOrder = DB::transaction(function () use ($order, $document, $actorId): Order {
            $locked = Order::query()->where('organization_id', $order->organization_id)->lockForUpdate()->findOrFail($order->id);
            if ($locked->warehouse_status === OrderWarehouseStatus::Dispatched) {
                $replayed = $locked->fresh(['items', 'dispatchDocument.items']);
                $this->economicEvents->recordDispatch($replayed, $replayed->dispatchDocument, $actorId);

                return $replayed;
            }
            if ((int) $locked->dispatch_document_id !== (int) $document->id
                || $locked->warehouse_status !== OrderWarehouseStatus::DispatchRequested) {
                throw ValidationException::withMessages(['order' => 'El pedido no tiene una solicitud de despacho confirmable.']);
            }

            $confirmed = $this->documents->confirm($document->id, $actorId);
            if ($confirmed->status !== InventoryDocumentStatus::Confirmed) {
                throw ValidationException::withMessages(['document' => 'El documento de despacho no fue confirmado.']);
            }

            $locked->items()->where('reserved_quantity', '>', 0)->update([
                'dispatched_quantity' => DB::raw('reserved_quantity'),
                'updated_at' => now(),
            ]);
            $locked->forceFill([
                'status' => 'fulfilled',
                'warehouse_status' => OrderWarehouseStatus::Dispatched->value,
                'dispatched_at' => now(),
            ])->save();

            $confirmedOrder = $locked->fresh(['items', 'dispatchDocument.items']);
            $this->economicEvents->recordDispatch($confirmedOrder, $confirmedOrder->dispatchDocument, $actorId);

            return $confirmedOrder;
        });

        return $confirmedOrder;
    }

    public function cancel(Order $order, ?int $actorId = null): Order
    {
        return DB::transaction(function () use ($order, $actorId): Order {
            $locked = Order::query()->where('organization_id', $order->organization_id)->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'cancelled' && $locked->warehouse_status === OrderWarehouseStatus::Released) {
                return $locked;
            }
            if (in_array($locked->warehouse_status, [OrderWarehouseStatus::Dispatched, OrderWarehouseStatus::ReturnRequested, OrderWarehouseStatus::Returned], true)) {
                throw ValidationException::withMessages(['order' => 'Un pedido despachado requiere una devolucion fisica; no puede cancelarse directamente.']);
            }
            if ($locked->inventory_reservation_id) {
                $reservation = InventoryReservation::query()->where('organization_id', $locked->organization_id)->findOrFail($locked->inventory_reservation_id);
                if ($reservation->status === InventoryReservationStatus::Active) {
                    $this->reservations->release(
                        (int) $locked->organization_id,
                        (int) $reservation->id,
                        "sales-order:{$locked->id}:cancel",
                        $actorId,
                        ['order_id' => $locked->id],
                    );
                }
            }

            $locked->items()->update(['reserved_quantity' => 0, 'updated_at' => now()]);
            $locked->forceFill([
                'status' => 'cancelled',
                'warehouse_status' => OrderWarehouseStatus::Released->value,
                'cancelled_at' => now(),
            ])->save();

            return $locked->fresh(['items', 'inventoryReservation']);
        });
    }

    public function requestReturn(Order $order, int $creditNoteId, ?int $actorId = null): InventoryDocument
    {
        return DB::transaction(function () use ($order, $creditNoteId, $actorId): InventoryDocument {
            $locked = Order::query()->where('organization_id', $order->organization_id)->lockForUpdate()->findOrFail($order->id);
            if ($locked->return_document_id) {
                $existingReturn = InventoryDocument::query()->where('organization_id', $locked->organization_id)->findOrFail($locked->return_document_id);
                if ((int) data_get($existingReturn->meta, 'credit_note_id') !== $creditNoteId) {
                    throw ValidationException::withMessages(['credit_note' => 'El pedido ya tiene una devolucion asociada a otra nota de credito.']);
                }

                return $existingReturn;
            }
            if ($locked->warehouse_status !== OrderWarehouseStatus::Dispatched) {
                throw ValidationException::withMessages(['order' => 'Solo un pedido despachado puede solicitar devolucion fisica.']);
            }
            $creditNote = DB::table('billing_documents')
                ->where('organization_id', $locked->organization_id)
                ->where('order_id', $locked->id)
                ->where('id', $creditNoteId)
                ->where('document_type', 'credit_note')
                ->where('status', 'issued')
                ->first();
            if (! $creditNote) {
                throw ValidationException::withMessages(['credit_note' => 'La nota de credito emitida no pertenece al pedido.']);
            }
            $originalDocument = DB::table('billing_documents')
                ->where('organization_id', $locked->organization_id)
                ->where('order_id', $locked->id)
                ->where('id', $creditNote->related_document_id)
                ->whereIn('document_type', ['factura', 'boleta'])
                ->first();
            if (! $originalDocument || round((float) $creditNote->total, 2) !== round((float) $originalDocument->total, 2)) {
                throw ValidationException::withMessages([
                    'credit_note' => 'FASE 06 solo permite devolucion fisica total; una nota parcial requiere lineas y cantidades explicitas.',
                ]);
            }

            $items = $locked->items()->with('product')->where('dispatched_quantity', '>', 0)->get();
            $document = $this->documents->createDraft([
                'organization_id' => $locked->organization_id,
                'document_type' => 'customer_return',
                'branch_id' => $locked->branch_id,
                'warehouse_id' => $locked->warehouse_id,
                'idempotency_key' => "credit-note:{$creditNoteId}:return",
                'external_reference' => (string) $creditNote->series.'-'.$creditNote->number,
                'reason' => 'credit_note_customer_return',
                'created_by' => $actorId,
                'items' => $items->map(fn ($item) => [
                    'product_id' => (int) $item->product_id,
                    'quantity' => (int) $item->dispatched_quantity,
                    'unit_cost' => (float) ($item->product?->purchase_price ?: $item->product?->average_price ?: 0),
                ])->values()->all(),
                'meta' => ['order_id' => $locked->id, 'credit_note_id' => $creditNoteId],
            ]);
            $locked->forceFill([
                'return_document_id' => $document->id,
                'warehouse_status' => OrderWarehouseStatus::ReturnRequested->value,
                'return_requested_at' => now(),
            ])->save();
            DB::table('billing_documents')->where('id', $creditNoteId)->update(['return_requested_at' => now(), 'updated_at' => now()]);

            return $document;
        });
    }

    public function confirmReturn(Order $order, int $creditNoteId, ?int $actorId = null): Order
    {
        $document = $this->requestReturn($order, $creditNoteId, $actorId);

        $returnedOrder = DB::transaction(function () use ($order, $document, $actorId): Order {
            $locked = Order::query()->where('organization_id', $order->organization_id)->lockForUpdate()->findOrFail($order->id);
            if ($locked->warehouse_status === OrderWarehouseStatus::Returned) {
                $replayed = $locked->fresh(['items', 'returnDocument.items']);
                $this->economicEvents->recordReturn($replayed, $replayed->returnDocument, $actorId);

                return $replayed;
            }
            if ((int) $locked->return_document_id !== (int) $document->id
                || $locked->warehouse_status !== OrderWarehouseStatus::ReturnRequested) {
                throw ValidationException::withMessages(['order' => 'El pedido no tiene una devolucion confirmable.']);
            }

            $this->documents->confirm($document->id, $actorId);
            $locked->items()->where('dispatched_quantity', '>', 0)->update([
                'returned_quantity' => DB::raw('dispatched_quantity'),
                'updated_at' => now(),
            ]);
            $locked->forceFill([
                'status' => 'refunded',
                'payment_status' => 'refunded',
                'warehouse_status' => OrderWarehouseStatus::Returned->value,
                'returned_at' => now(),
            ])->save();

            $returnedOrder = $locked->fresh(['items', 'returnDocument.items']);
            $this->economicEvents->recordReturn($returnedOrder, $returnedOrder->returnDocument, $actorId);

            return $returnedOrder;
        });

        return $returnedOrder;
    }

    private function orderCode(Order $order): string
    {
        return $order->series.'-'.str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT);
    }
}
