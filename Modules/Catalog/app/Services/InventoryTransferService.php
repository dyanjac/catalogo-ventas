<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Services\OrganizationContextService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Data\InventoryTransferCommand;
use Modules\Catalog\Data\InventoryTransferItemData;
use Modules\Catalog\Data\InventoryTransferReceiptCommand;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryLedgerRollout;
use Modules\Catalog\Entities\InventoryTransfer;
use Modules\Catalog\Entities\InventoryTransferEvent;
use Modules\Catalog\Entities\InventoryTransferEventItem;
use Modules\Catalog\Entities\InventoryTransferItem;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;
use Modules\Catalog\Enums\InventoryTransferEventType;
use Modules\Catalog\Enums\InventoryTransferStatus;

class InventoryTransferService
{
    public function __construct(
        private readonly InventoryMovementService $movements,
        private readonly OrganizationContextService $organizationContext,
    ) {}

    public function create(InventoryTransferCommand $command): InventoryTransfer
    {
        $this->assertOperationalOrganization($command->organizationId);
        $this->assertIdempotencyKey($command->idempotencyKey);
        $items = $this->normalizeItems($command->items);
        $payloadHash = $this->hash([
            'organization_id' => $command->organizationId,
            'source_warehouse_id' => $command->sourceWarehouseId,
            'destination_warehouse_id' => $command->destinationWarehouseId,
            'items' => $items,
            'actor_id' => $command->actorId,
            'notes' => $command->notes,
        ]);
        $existing = $this->findByKey($command->organizationId, $command->idempotencyKey);
        if ($existing) {
            return $this->validateTransferReplay($existing, $payloadHash);
        }

        try {
            return DB::transaction(function () use ($command, $items, $payloadHash): InventoryTransfer {
                $this->lockActiveRollout($command->organizationId);
                $existing = $this->findByKey($command->organizationId, $command->idempotencyKey, true);
                if ($existing) {
                    return $this->validateTransferReplay($existing, $payloadHash);
                }

                [$source, $destination] = $this->warehouses($command->organizationId, $command->sourceWarehouseId, $command->destinationWarehouseId);
                $products = Product::query()
                    ->where('organization_id', $command->organizationId)
                    ->whereIn('id', array_keys($items))
                    ->get()
                    ->keyBy('id');
                if ($products->count() !== count($items) || $products->contains(fn (Product $product) => ! $product->is_active || ! $product->tracksInventory())) {
                    throw ValidationException::withMessages(['items' => 'Todos los productos deben existir, estar activos y controlar inventario.']);
                }

                $sourceBalances = $this->balancesForWarehouse($command->organizationId, $source->id, array_keys($items));
                $destinationBalances = $this->balancesForWarehouse($command->organizationId, $destination->id, array_keys($items));
                $this->assertLegacyCoverage($command->organizationId, $source, $destination, array_keys($items));

                $transfer = InventoryTransfer::query()->create([
                    'organization_id' => $command->organizationId,
                    'code' => 'TRF-'.strtoupper(substr(sha1($command->organizationId.':'.$command->idempotencyKey), 0, 12)),
                    'idempotency_key' => $command->idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'source_branch_id' => $source->branch_id,
                    'destination_branch_id' => $destination->branch_id,
                    'source_warehouse_id' => $source->id,
                    'destination_warehouse_id' => $destination->id,
                    'status' => InventoryTransferStatus::Draft->value,
                    'created_by' => $command->actorId,
                    'notes' => $command->notes,
                ]);
                foreach ($items as $productId => $quantity) {
                    InventoryTransferItem::query()->create([
                        'organization_id' => $command->organizationId,
                        'transfer_id' => $transfer->id,
                        'product_id' => $productId,
                        'source_balance_id' => $sourceBalances->get($productId)->id,
                        'destination_balance_id' => $destinationBalances->get($productId)->id,
                        'quantity' => $quantity,
                    ]);
                }
                $this->recordEvent(
                    $transfer,
                    $command->idempotencyKey.':created',
                    $this->hash(['transfer' => $payloadHash, 'event' => 'created']),
                    InventoryTransferEventType::Created,
                    null,
                    InventoryTransferStatus::Draft,
                    $command->actorId,
                    $command->notes,
                );

                return $transfer->load(['items.product', 'sourceWarehouse', 'destinationWarehouse', 'events.items']);
            }, $this->attempts());
        } catch (QueryException $exception) {
            $existing = $this->findByKey($command->organizationId, $command->idempotencyKey);
            if (! $existing) {
                throw $exception;
            }

            return $this->validateTransferReplay($existing, $payloadHash);
        }
    }

    public function dispatch(int $organizationId, int $transferId, string $idempotencyKey, ?int $actorId = null): InventoryTransfer
    {
        $this->assertOperationalOrganization($organizationId);
        $this->assertIdempotencyKey($idempotencyKey);
        $payloadHash = $this->hash(['organization_id' => $organizationId, 'transfer_id' => $transferId, 'event' => 'dispatch', 'actor_id' => $actorId]);

        return DB::transaction(function () use ($organizationId, $transferId, $idempotencyKey, $actorId, $payloadHash): InventoryTransfer {
            $this->lockActiveRollout($organizationId);
            $event = $this->eventByKey($organizationId, $idempotencyKey);
            if ($event) {
                return $this->validateEventReplay($event, $transferId, InventoryTransferEventType::Dispatched, $payloadHash);
            }
            $transfer = InventoryTransfer::query()
                ->where('organization_id', $organizationId)
                ->with(['items.product', 'sourceWarehouse', 'destinationWarehouse'])
                ->lockForUpdate()
                ->findOrFail($transferId);
            $event = $this->eventByKey($organizationId, $idempotencyKey, true);
            if ($event) {
                return $this->validateEventReplay($event, $transferId, InventoryTransferEventType::Dispatched, $payloadHash);
            }
            if ($transfer->status !== InventoryTransferStatus::Draft) {
                throw ValidationException::withMessages(['transfer' => 'Solo una transferencia en borrador puede despacharse.']);
            }
            if (! $transfer->sourceWarehouse?->is_active || ! $transfer->destinationWarehouse?->is_active) {
                throw ValidationException::withMessages(['warehouse' => 'Los almacenes de la transferencia deben estar activos.']);
            }

            $items = $transfer->items->sortBy('source_balance_id')->values();
            $this->prelockOperationRows($transfer, $items->pluck('product_id')->map(fn ($id) => (int) $id)->all());
            $event = $this->recordEvent(
                $transfer,
                $idempotencyKey,
                $payloadHash,
                InventoryTransferEventType::Dispatched,
                InventoryTransferStatus::Draft,
                InventoryTransferStatus::InTransit,
                $actorId,
            );

            foreach ($items as $item) {
                $sourceBalance = InventoryBalance::query()
                    ->where('organization_id', $organizationId)
                    ->lockForUpdate()
                    ->findOrFail($item->source_balance_id);
                $destinationBalance = InventoryBalance::query()
                    ->where('organization_id', $organizationId)
                    ->lockForUpdate()
                    ->findOrFail($item->destination_balance_id);
                $quantity = (int) $item->quantity;
                $movement = $this->movements->recordWarehouseOutbound(
                    $item->product,
                    (int) $transfer->source_branch_id,
                    (int) $transfer->source_warehouse_id,
                    $quantity,
                    [
                        'idempotency_key' => $idempotencyKey.':item:'.$item->id,
                        'reason_code' => 'transfer',
                        'reason' => 'warehouse_transfer_dispatch',
                        'unit_cost' => (float) $sourceBalance->average_cost,
                        'performed_by' => $actorId,
                        'reference_type' => InventoryTransfer::class,
                        'reference_id' => $transfer->id,
                        'reference_code' => $transfer->code,
                        'meta' => ['destination_warehouse_id' => $transfer->destination_warehouse_id],
                    ]
                );
                InventoryBalance::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($destinationBalance->id)
                    ->update([
                        'in_transit_stock' => DB::raw('in_transit_stock + '.$quantity),
                        'transit_version' => DB::raw('transit_version + 1'),
                    ]);
                $item->forceFill([
                    'dispatched_quantity' => $quantity,
                    'unit_cost' => $movement->unit_cost,
                ])->save();
                InventoryTransferEventItem::query()->create([
                    'organization_id' => $organizationId,
                    'event_id' => $event->id,
                    'transfer_item_id' => $item->id,
                    'quantity' => $quantity,
                    'transit_delta' => $quantity,
                    'inventory_movement_id' => $movement->id,
                ]);
            }

            $transfer->forceFill([
                'status' => InventoryTransferStatus::InTransit->value,
                'dispatched_at' => now(),
                'dispatched_by' => $actorId,
            ])->save();

            return $transfer->fresh(['items.product', 'sourceWarehouse', 'destinationWarehouse', 'events.items']);
        }, $this->attempts());
    }

    public function receive(InventoryTransferReceiptCommand $command): InventoryTransfer
    {
        $this->assertOperationalOrganization($command->organizationId);
        $this->assertIdempotencyKey($command->idempotencyKey);
        $quantities = $this->normalizeReceiptQuantities($command->quantitiesByItemId);
        $payloadHash = $this->hash([
            'organization_id' => $command->organizationId,
            'transfer_id' => $command->transferId,
            'event' => 'receive',
            'items' => $quantities,
            'actor_id' => $command->actorId,
            'notes' => $command->notes,
        ]);

        return DB::transaction(function () use ($command, $quantities, $payloadHash): InventoryTransfer {
            $this->lockActiveRollout($command->organizationId);
            $event = $this->eventByKey($command->organizationId, $command->idempotencyKey);
            if ($event) {
                return $this->validateEventReplay($event, $command->transferId, InventoryTransferEventType::Received, $payloadHash);
            }
            $transfer = InventoryTransfer::query()
                ->where('organization_id', $command->organizationId)
                ->lockForUpdate()
                ->findOrFail($command->transferId);
            $event = $this->eventByKey($command->organizationId, $command->idempotencyKey, true);
            if ($event) {
                return $this->validateEventReplay($event, $command->transferId, InventoryTransferEventType::Received, $payloadHash);
            }
            if (! in_array($transfer->status, [InventoryTransferStatus::InTransit, InventoryTransferStatus::PartiallyReceived], true)) {
                throw ValidationException::withMessages(['transfer' => 'La transferencia no admite recepciones en su estado actual.']);
            }

            $transfer->load(['sourceWarehouse', 'destinationWarehouse']);
            $lockedItems = InventoryTransferItem::query()
                ->where('organization_id', $command->organizationId)
                ->where('transfer_id', $transfer->id)
                ->with('product')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $transfer->setRelation('items', $lockedItems);
            $items = $lockedItems->keyBy('id');
            foreach ($quantities as $itemId => $quantity) {
                $item = $items->get((int) $itemId);
                if (! $item || (int) $quantity > (int) $item->dispatched_quantity - (int) $item->received_quantity) {
                    throw ValidationException::withMessages(['items' => 'La recepcion excede la cantidad pendiente o contiene un item ajeno.']);
                }
            }
            $this->prelockOperationRows($transfer, $items->pluck('product_id')->map(fn ($id) => (int) $id)->all());
            $before = $transfer->status;
            $willComplete = $items->every(function (InventoryTransferItem $item) use ($quantities): bool {
                return (int) $item->received_quantity + (int) ($quantities[$item->id] ?? 0) === (int) $item->dispatched_quantity;
            });
            $after = $willComplete ? InventoryTransferStatus::Received : InventoryTransferStatus::PartiallyReceived;
            $event = $this->recordEvent(
                $transfer,
                $command->idempotencyKey,
                $payloadHash,
                InventoryTransferEventType::Received,
                $before,
                $after,
                $command->actorId,
                $command->notes,
            );

            foreach ($quantities as $itemId => $quantity) {
                /** @var InventoryTransferItem $item */
                $item = $items->get((int) $itemId);
                $balance = InventoryBalance::query()->where('organization_id', $command->organizationId)->findOrFail($item->destination_balance_id);
                $movement = $this->movements->recordTransitInbound($balance, (int) $quantity, [
                    'idempotency_key' => $command->idempotencyKey.':item:'.$item->id,
                    'reason_code' => 'transfer',
                    'reason' => 'warehouse_transfer_receipt',
                    'unit_cost' => (float) $item->unit_cost,
                    'performed_by' => $command->actorId,
                    'reference_type' => InventoryTransfer::class,
                    'reference_id' => $transfer->id,
                    'reference_code' => $transfer->code,
                    'notes' => $command->notes,
                    'meta' => ['source_warehouse_id' => $transfer->source_warehouse_id, 'transfer_event_id' => $event->id],
                ]);
                $item->forceFill(['received_quantity' => (int) $item->received_quantity + (int) $quantity])->save();
                InventoryTransferEventItem::query()->create([
                    'organization_id' => $command->organizationId,
                    'event_id' => $event->id,
                    'transfer_item_id' => $item->id,
                    'quantity' => (int) $quantity,
                    'transit_delta' => (int) $quantity * -1,
                    'inventory_movement_id' => $movement->id,
                ]);
            }

            $transfer->forceFill([
                'status' => $after->value,
                'completed_at' => $after === InventoryTransferStatus::Received ? now() : null,
                'completed_by' => $after === InventoryTransferStatus::Received ? $command->actorId : null,
            ])->save();

            return $transfer->fresh(['items.product', 'sourceWarehouse', 'destinationWarehouse', 'events.items']);
        }, $this->attempts());
    }

    public function cancelDraft(int $organizationId, int $transferId, string $idempotencyKey, ?int $actorId = null): InventoryTransfer
    {
        $this->assertOperationalOrganization($organizationId);
        $this->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use ($organizationId, $transferId, $idempotencyKey, $actorId): InventoryTransfer {
            $transfer = InventoryTransfer::query()->where('organization_id', $organizationId)->lockForUpdate()->findOrFail($transferId);
            $hash = $this->hash(['organization_id' => $organizationId, 'transfer_id' => $transferId, 'event' => 'cancel', 'actor_id' => $actorId]);
            $event = $this->eventByKey($organizationId, $idempotencyKey, true);
            if ($event) {
                return $this->validateEventReplay($event, $transferId, InventoryTransferEventType::Cancelled, $hash);
            }
            if ($transfer->status !== InventoryTransferStatus::Draft) {
                throw ValidationException::withMessages(['transfer' => 'Solo una transferencia en borrador puede cancelarse.']);
            }
            $this->recordEvent($transfer, $idempotencyKey, $hash, InventoryTransferEventType::Cancelled, InventoryTransferStatus::Draft, InventoryTransferStatus::Cancelled, $actorId);
            $transfer->forceFill(['status' => InventoryTransferStatus::Cancelled->value, 'cancelled_at' => now(), 'cancelled_by' => $actorId])->save();

            return $transfer->fresh(['items', 'events.items']);
        }, $this->attempts());
    }

    public function transferProduct(Product $product, int $sourceBranchId, int $destinationBranchId, int $quantity, array $context = []): InventoryTransfer
    {
        $organizationId = (int) $product->organization_id;
        $source = InventoryWarehouse::query()->where('organization_id', $organizationId)->where('branch_id', $sourceBranchId)->where('is_default', true)->where('is_active', true)->first();
        $destination = InventoryWarehouse::query()->where('organization_id', $organizationId)->where('branch_id', $destinationBranchId)->where('is_default', true)->where('is_active', true)->first();
        if (! $source || ! $destination) {
            throw ValidationException::withMessages(['warehouse' => 'Cada sucursal requiere un almacen predeterminado activo para transferir.']);
        }
        $key = (string) ($context['idempotency_key'] ?? Str::uuid());
        $transfer = $this->create(new InventoryTransferCommand(
            organizationId: $organizationId,
            idempotencyKey: $key,
            sourceWarehouseId: (int) $source->id,
            destinationWarehouseId: (int) $destination->id,
            items: [new InventoryTransferItemData((int) $product->id, $quantity)],
            actorId: $context['created_by'] ?? null,
            notes: $context['notes'] ?? null,
        ));

        return $this->dispatch($organizationId, (int) $transfer->id, $key.':dispatch', $context['created_by'] ?? null);
    }

    /** @param array<int, InventoryTransferItemData> $items @return array<int, int> */
    private function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if ($item->productId < 1 || $item->quantity < 1) {
                throw ValidationException::withMessages(['items' => 'Cada item requiere producto y cantidad positiva.']);
            }
            $normalized[$item->productId] = ($normalized[$item->productId] ?? 0) + $item->quantity;
        }
        if ($normalized === []) {
            throw ValidationException::withMessages(['items' => 'La transferencia requiere items.']);
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /** @return array{0:InventoryWarehouse,1:InventoryWarehouse} */
    private function warehouses(int $organizationId, int $sourceId, int $destinationId): array
    {
        if ($sourceId === $destinationId) {
            throw ValidationException::withMessages(['destination_warehouse_id' => 'El almacen destino debe ser distinto al origen.']);
        }
        $warehouses = InventoryWarehouse::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereIn('id', [$sourceId, $destinationId])
            ->get()->keyBy('id');
        if ($warehouses->count() !== 2) {
            throw ValidationException::withMessages(['warehouse' => 'Origen y destino deben existir, estar activos y pertenecer a la organizacion.']);
        }

        return [$warehouses->get($sourceId), $warehouses->get($destinationId)];
    }

    private function balancesForWarehouse(int $organizationId, int $warehouseId, array $productIds): Collection
    {
        $balances = InventoryBalance::query()
            ->where('organization_id', $organizationId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->whereIn('product_id', $productIds)
            ->get()->keyBy('product_id');
        if ($balances->count() !== count($productIds)) {
            throw ValidationException::withMessages(['items' => 'Todos los productos requieren un saldo ledger activo en ambos almacenes.']);
        }

        return $balances;
    }

    private function assertLegacyCoverage(int $organizationId, InventoryWarehouse $source, InventoryWarehouse $destination, array $productIds): void
    {
        foreach ([$source, $destination] as $warehouse) {
            $count = ProductWarehouseStock::query()
                ->where('organization_id', $organizationId)
                ->where('branch_id', $warehouse->branch_id)
                ->where('warehouse_id', $warehouse->id)
                ->where('is_active', true)
                ->whereIn('product_id', $productIds)
                ->count();
            if ($count !== count($productIds)) {
                throw ValidationException::withMessages(['items' => 'Los productos deben estar habilitados en ambos almacenes.']);
            }
        }
    }

    private function prelockOperationRows(InventoryTransfer $transfer, array $productIds): void
    {
        ProductBranchStock::query()
            ->where('organization_id', $transfer->organization_id)
            ->whereIn('branch_id', [$transfer->source_branch_id, $transfer->destination_branch_id])
            ->whereIn('product_id', $productIds)
            ->orderBy('id')->lockForUpdate()->get();
        ProductWarehouseStock::query()
            ->where('organization_id', $transfer->organization_id)
            ->whereIn('warehouse_id', [$transfer->source_warehouse_id, $transfer->destination_warehouse_id])
            ->whereIn('product_id', $productIds)
            ->orderBy('id')->lockForUpdate()->get();
        InventoryBalance::query()
            ->where('organization_id', $transfer->organization_id)
            ->whereIn('id', $transfer->items->flatMap(fn ($item) => [$item->source_balance_id, $item->destination_balance_id])->all())
            ->orderBy('id')->lockForUpdate()->get();
    }

    private function recordEvent(InventoryTransfer $transfer, string $key, string $hash, InventoryTransferEventType $type, ?InventoryTransferStatus $before, InventoryTransferStatus $after, ?int $actorId, ?string $notes = null): InventoryTransferEvent
    {
        return InventoryTransferEvent::query()->create([
            'organization_id' => $transfer->organization_id,
            'transfer_id' => $transfer->id,
            'idempotency_key' => $key,
            'payload_hash' => $hash,
            'event_type' => $type->value,
            'status_before' => $before?->value,
            'status_after' => $after->value,
            'performed_by' => $actorId,
            'occurred_at' => now(),
            'notes' => $notes,
        ]);
    }

    private function findByKey(int $organizationId, string $key, bool $lock = false): ?InventoryTransfer
    {
        $query = InventoryTransfer::query()->where('organization_id', $organizationId)->where('idempotency_key', $key);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->with(['items.product', 'sourceWarehouse', 'destinationWarehouse', 'events.items'])->first();
    }

    private function eventByKey(int $organizationId, string $key, bool $lock = false): ?InventoryTransferEvent
    {
        $query = InventoryTransferEvent::query()->where('organization_id', $organizationId)->where('idempotency_key', $key);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function validateTransferReplay(InventoryTransfer $transfer, string $hash): InventoryTransfer
    {
        if (! hash_equals((string) $transfer->payload_hash, $hash)) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave ya fue usada con un contenido diferente.']);
        }

        return $transfer;
    }

    private function validateEventReplay(InventoryTransferEvent $event, int $transferId, InventoryTransferEventType $type, string $hash): InventoryTransfer
    {
        if ((int) $event->transfer_id !== $transferId || $event->event_type !== $type || ! hash_equals((string) $event->payload_hash, $hash)) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave ya fue usada con un contenido diferente.']);
        }

        return InventoryTransfer::query()
            ->where('organization_id', $event->organization_id)
            ->with(['items.product', 'sourceWarehouse', 'destinationWarehouse', 'events.items'])
            ->lockForUpdate()
            ->findOrFail($transferId);
    }

    private function lockActiveRollout(int $organizationId): void
    {
        $mode = InventoryLedgerRollout::query()->where('organization_id', $organizationId)->sharedLock()->first()?->mode;
        if ($mode !== InventoryLedgerRolloutMode::Active) {
            throw ValidationException::withMessages(['rollout' => 'Las operaciones de almacen requieren ledger active.']);
        }
    }

    private function assertOperationalOrganization(int $organizationId): void
    {
        $current = (int) $this->organizationContext->currentOrganizationId();
        if ($organizationId < 1 || ($current > 0 && $current !== $organizationId) || $this->organizationContext->isSuspended()) {
            throw ValidationException::withMessages(['organization_id' => 'La organizacion no coincide con el contexto operativo activo.']);
        }
    }

    private function assertIdempotencyKey(string $key): void
    {
        if ($key === '' || mb_strlen($key) > 120) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave de idempotencia es obligatoria y admite hasta 120 caracteres.']);
        }
    }

    /** @param array<int, int> $quantities @return array<int, int> */
    private function normalizeReceiptQuantities(array $quantities): array
    {
        $normalized = [];
        foreach ($quantities as $itemId => $quantity) {
            if (! is_int($itemId) && ! ctype_digit((string) $itemId)) {
                throw ValidationException::withMessages(['items' => 'La recepcion contiene un identificador de item invalido.']);
            }
            if (filter_var($quantity, FILTER_VALIDATE_INT) === false || (int) $quantity < 1) {
                throw ValidationException::withMessages(['items' => 'Cada cantidad recibida debe ser un entero positivo.']);
            }
            $normalized[(int) $itemId] = (int) $quantity;
        }
        if ($normalized === []) {
            throw ValidationException::withMessages(['items' => 'La recepcion requiere al menos una cantidad positiva.']);
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode(Arr::sortRecursive($payload), JSON_THROW_ON_ERROR));
    }

    private function attempts(): int
    {
        return max(1, (int) config('catalog.reservations.transaction_attempts', 5));
    }
}
