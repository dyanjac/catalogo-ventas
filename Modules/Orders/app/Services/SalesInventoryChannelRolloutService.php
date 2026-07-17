<?php

declare(strict_types=1);

namespace Modules\Orders\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Entities\InventoryLedgerRollout;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;
use Modules\Orders\Entities\Order;
use Modules\Orders\Entities\SalesInventoryChannelRollout;
use Modules\Orders\Enums\OrderWarehouseStatus;
use Modules\Orders\Enums\SalesInventoryChannelMode;

class SalesInventoryChannelRolloutService
{
    public function mode(int $organizationId, string $channel): SalesInventoryChannelMode
    {
        $stored = SalesInventoryChannelRollout::query()
            ->where('organization_id', $organizationId)
            ->where('channel', $channel)
            ->first()?->mode;

        if ($stored instanceof SalesInventoryChannelMode) {
            return $stored;
        }

        return SalesInventoryChannelMode::tryFrom((string) config("orders.inventory_channels.{$channel}", 'legacy'))
            ?? SalesInventoryChannelMode::Legacy;
    }

    public function isActive(int $organizationId, string $channel): bool
    {
        return $this->mode($organizationId, $channel) === SalesInventoryChannelMode::Active;
    }

    public function setMode(int $organizationId, string $channel, SalesInventoryChannelMode $mode, array $meta = []): SalesInventoryChannelRollout
    {
        if (! in_array($channel, ['ecommerce', 'pos'], true)) {
            throw ValidationException::withMessages(['channel' => 'Canal de ventas no soportado.']);
        }

        return DB::transaction(function () use ($organizationId, $channel, $mode, $meta): SalesInventoryChannelRollout {
            if ($mode === SalesInventoryChannelMode::Active) {
                $ledgerMode = InventoryLedgerRollout::query()
                    ->where('organization_id', $organizationId)
                    ->sharedLock()
                    ->first()?->mode;
                if ($ledgerMode !== InventoryLedgerRolloutMode::Active) {
                    throw ValidationException::withMessages(['mode' => 'El canal requiere el ledger de inventario en modo active.']);
                }
                if (! InventoryWarehouse::query()->where('organization_id', $organizationId)->where('is_default', true)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['warehouse' => 'El canal requiere al menos un almacen predeterminado activo.']);
                }
            } elseif (Order::query()
                ->where('organization_id', $organizationId)
                ->where('sales_channel', $channel)
                ->whereIn('warehouse_status', [
                    OrderWarehouseStatus::Reserved->value,
                    OrderWarehouseStatus::DispatchRequested->value,
                ])->exists()) {
                throw ValidationException::withMessages(['mode' => 'No se puede desactivar el canal mientras existan reservas o despachos abiertos.']);
            }

            return SalesInventoryChannelRollout::query()->updateOrCreate(
                ['organization_id' => $organizationId, 'channel' => $channel],
                [
                    'mode' => $mode->value,
                    'activated_at' => $mode === SalesInventoryChannelMode::Active ? now() : null,
                    'meta' => $meta,
                ]
            );
        });
    }
}
