<?php

declare(strict_types=1);

namespace Modules\Orders\Listeners;

use Modules\Catalog\Entities\InventoryReservation;
use Modules\Catalog\Enums\InventoryReservationStatus;
use Modules\Orders\Entities\Order;
use Modules\Orders\Enums\OrderWarehouseStatus;

class SynchronizeExpiredOrderReservation
{
    public function handle(InventoryReservation $reservation): void
    {
        if (! $reservation->wasChanged('status')
            || $reservation->status !== InventoryReservationStatus::Expired
            || $reservation->source_type !== Order::class
            || ! $reservation->source_id) {
            return;
        }

        $order = Order::query()
            ->where('organization_id', $reservation->organization_id)
            ->whereKey($reservation->source_id)
            ->where('inventory_reservation_id', $reservation->id)
            ->whereIn('warehouse_status', [
                OrderWarehouseStatus::Reserved->value,
                OrderWarehouseStatus::DispatchRequested->value,
            ])
            ->first();
        if (! $order) {
            return;
        }

        $order->items()->update(['reserved_quantity' => 0, 'updated_at' => now()]);
        $order->forceFill([
            'warehouse_status' => OrderWarehouseStatus::ReservationExpired->value,
            'dispatch_document_id' => null,
            'dispatch_requested_at' => null,
        ])->save();
    }
}
