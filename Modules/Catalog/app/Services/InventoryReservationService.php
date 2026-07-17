<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Data\InventoryReservationCommand;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryLedgerRollout;
use Modules\Catalog\Entities\InventoryReservation;
use Modules\Catalog\Entities\InventoryReservationEvent;
use Modules\Catalog\Entities\InventoryReservationItem;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;
use Modules\Catalog\Enums\InventoryReservationEventType;
use Modules\Catalog\Enums\InventoryReservationStatus;
use RuntimeException;

class InventoryReservationService
{
    public function __construct(private readonly InventoryMovementService $movements) {}

    public function reserve(InventoryReservationCommand $command): InventoryReservation
    {
        $items = $this->normalizeItems($command);
        $payloadHash = $this->reservationPayloadHash($command, $items);
        $existing = InventoryReservation::query()
            ->where('organization_id', $command->organizationId)
            ->where('idempotency_key', $command->idempotencyKey)
            ->first();
        if ($existing) {
            return $this->validateReservationReplay($existing, $payloadHash);
        }

        $this->assertLedgerActive($command->organizationId);
        $expiresAt = $this->resolvedExpiry($command);

        for ($collisionAttempt = 0; $collisionAttempt < 2; $collisionAttempt++) {
            try {
                return DB::transaction(function () use ($command, $items, $payloadHash, $expiresAt): InventoryReservation {
                    $existing = InventoryReservation::query()
                        ->where('organization_id', $command->organizationId)
                        ->where('idempotency_key', $command->idempotencyKey)
                        ->first();

                    if ($existing) {
                        return $this->validateReservationReplay($existing, $payloadHash);
                    }

                    $this->lockLedgerActive($command->organizationId);

                    $balances = $this->lockBalances($command->organizationId, array_keys($items));

                    // Current read: otra transaccion pudo confirmar la misma clave mientras esperabamos los saldos.
                    $existing = InventoryReservation::query()
                        ->where('organization_id', $command->organizationId)
                        ->where('idempotency_key', $command->idempotencyKey)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        return $this->validateReservationReplay($existing, $payloadHash);
                    }
                    foreach ($items as $balanceId => $quantity) {
                        /** @var InventoryBalance $balance */
                        $balance = $balances->get($balanceId);
                        if ($balance->availableStock() < $quantity) {
                            throw ValidationException::withMessages([
                                "items.{$balanceId}" => "Stock disponible insuficiente: {$balance->availableStock()}.",
                            ]);
                        }
                    }

                    $reservation = InventoryReservation::query()->create([
                        'organization_id' => $command->organizationId,
                        'idempotency_key' => $command->idempotencyKey,
                        'payload_hash' => $payloadHash,
                        'status' => InventoryReservationStatus::Active->value,
                        'source_type' => $command->sourceType,
                        'source_id' => $command->sourceId,
                        'source_code' => $command->sourceCode,
                        'expires_at' => $expiresAt,
                        'created_by' => $command->actorId,
                        'meta' => $command->meta,
                    ]);

                    $total = 0;
                    foreach ($items as $balanceId => $quantity) {
                        /** @var InventoryBalance $balance */
                        $balance = $balances->get($balanceId);
                        $total += $quantity;
                        InventoryReservationItem::query()->create([
                            'organization_id' => $command->organizationId,
                            'reservation_id' => $reservation->id,
                            'inventory_balance_id' => $balance->id,
                            'product_id' => $balance->product_id,
                            'branch_id' => $balance->branch_id,
                            'warehouse_id' => $balance->warehouse_id,
                            'quantity' => $quantity,
                        ]);
                        $balance->forceFill([
                            'reserved_stock' => (int) $balance->reserved_stock + $quantity,
                            'reservation_version' => (int) $balance->reservation_version + 1,
                        ])->save();
                    }

                    $this->recordEvent(
                        $reservation,
                        $command->idempotencyKey,
                        $payloadHash,
                        InventoryReservationEventType::Reserved,
                        null,
                        InventoryReservationStatus::Active,
                        $total,
                        $command->actorId,
                        $command->meta,
                    );

                    return $reservation->load('items', 'events');
                }, $this->transactionAttempts());
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }

                $existing = InventoryReservation::query()
                    ->where('organization_id', $command->organizationId)
                    ->where('idempotency_key', $command->idempotencyKey)
                    ->first();
                if ($existing) {
                    return $this->validateReservationReplay($existing, $payloadHash);
                }
                if ($collisionAttempt === 1) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('No se pudo registrar la reserva de inventario.');
    }

    /** @param array<string, mixed> $meta */
    public function release(int $organizationId, int $reservationId, string $idempotencyKey, ?int $actorId = null, array $meta = []): InventoryReservation
    {
        return $this->transition(
            $organizationId,
            $reservationId,
            $idempotencyKey,
            InventoryReservationStatus::Released,
            InventoryReservationEventType::Released,
            $actorId,
            $meta,
        );
    }

    /** @param array<string, mixed> $meta */
    public function expire(int $organizationId, int $reservationId, string $idempotencyKey, ?int $actorId = null, array $meta = []): InventoryReservation
    {
        return $this->transition(
            $organizationId,
            $reservationId,
            $idempotencyKey,
            InventoryReservationStatus::Expired,
            InventoryReservationEventType::Expired,
            $actorId,
            $meta,
        );
    }

    /** @param array<string, mixed> $meta */
    public function consume(
        int $organizationId,
        int $reservationId,
        string $idempotencyKey,
        ?int $actorId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceCode = null,
        array $meta = [],
    ): InventoryReservation {
        $this->assertValidKey($idempotencyKey);
        $eventType = InventoryReservationEventType::Consumed;
        $payloadMeta = [...$meta, 'reference_type' => $referenceType, 'reference_id' => $referenceId, 'reference_code' => $referenceCode];
        $payloadHash = $this->transitionPayloadHash($organizationId, $reservationId, $eventType, $actorId, $payloadMeta);

        return DB::transaction(function () use ($organizationId, $reservationId, $idempotencyKey, $actorId, $referenceType, $referenceId, $referenceCode, $payloadMeta, $payloadHash, $eventType): InventoryReservation {
            $existingEvent = InventoryReservationEvent::query()
                ->where('organization_id', $organizationId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existingEvent) {
                return $this->validateTransitionReplay($existingEvent, $reservationId, $eventType, $payloadHash);
            }

            $reservation = InventoryReservation::query()
                ->where('organization_id', $organizationId)
                ->whereKey($reservationId)
                ->lockForUpdate()
                ->firstOrFail();
            $existingEvent = InventoryReservationEvent::query()
                ->where('organization_id', $organizationId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existingEvent) {
                return $this->validateTransitionReplay($existingEvent, $reservationId, $eventType, $payloadHash);
            }
            if ($reservation->status !== InventoryReservationStatus::Active || ($reservation->expires_at && $reservation->expires_at->isPast())) {
                throw ValidationException::withMessages(['reservation' => 'Solo una reserva activa y vigente puede consumirse.']);
            }

            $items = InventoryReservationItem::query()
                ->where('organization_id', $organizationId)
                ->where('reservation_id', $reservationId)
                ->orderBy('inventory_balance_id')
                ->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['reservation' => 'La reserva no contiene items.']);
            }

            $total = 0;
            foreach ($items as $item) {
                $balance = InventoryBalance::query()
                    ->where('organization_id', $organizationId)
                    ->findOrFail($item->inventory_balance_id);
                $this->movements->recordReservedOutbound($balance, (int) $item->quantity, [
                    'idempotency_key' => $idempotencyKey.':item:'.$item->id,
                    'reason_code' => 'dispatch',
                    'reason' => 'reserved_dispatch',
                    'performed_by' => $actorId,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'reference_code' => $referenceCode,
                    'meta' => [...$payloadMeta, 'reservation_id' => $reservationId, 'reservation_item_id' => $item->id],
                ]);
                $total += (int) $item->quantity;
            }

            $reservation->forceFill([
                'status' => InventoryReservationStatus::Consumed->value,
                'consumed_at' => now(),
                'terminal_actor_id' => $actorId,
            ])->save();
            $this->recordEvent(
                $reservation,
                $idempotencyKey,
                $payloadHash,
                $eventType,
                InventoryReservationStatus::Active,
                InventoryReservationStatus::Consumed,
                -$total,
                $actorId,
                $payloadMeta,
            );

            return $reservation->fresh(['items', 'events']);
        }, $this->transactionAttempts());
    }

    /** @return array{matched:int, processed:int} */
    public function expireDue(?int $organizationId = null, ?int $limit = null, bool $dryRun = false): array
    {
        $limit ??= (int) config('catalog.reservations.expire_batch_size', 500);
        $query = InventoryReservation::query()
            ->where('status', InventoryReservationStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->when($organizationId, fn ($builder) => $builder->where('organization_id', $organizationId))
            ->orderBy('id')
            ->limit(max(1, $limit));
        $reservations = $query->get(['id', 'organization_id', 'expires_at']);

        if ($dryRun) {
            return ['matched' => $reservations->count(), 'processed' => 0];
        }

        $processed = 0;
        foreach ($reservations as $reservation) {
            $key = 'reservation-expire:'.$reservation->id.':'.$reservation->expires_at->getTimestamp();
            try {
                $this->expire((int) $reservation->organization_id, (int) $reservation->id, $key, meta: ['source' => 'scheduler']);
                $processed++;
            } catch (ValidationException) {
                // Otra transaccion pudo liberar la reserva despues de seleccionar el lote.
            }
        }

        return ['matched' => $reservations->count(), 'processed' => $processed];
    }

    /** @return array{matched:int, processed:int} */
    public function releaseAllActive(int $organizationId, bool $dryRun = false): array
    {
        $baseQuery = InventoryReservation::query()
            ->where('organization_id', $organizationId)
            ->where('status', InventoryReservationStatus::Active->value);
        $maximumId = (int) (clone $baseQuery)->max('id');
        $matched = $maximumId > 0 ? (clone $baseQuery)->where('id', '<=', $maximumId)->count() : 0;

        if ($dryRun) {
            return ['matched' => $matched, 'processed' => 0];
        }

        $processed = 0;
        $batchSize = max(1, (int) config('catalog.reservations.expire_batch_size', 500));
        foreach ($baseQuery->where('id', '<=', $maximumId)->lazyById($batchSize) as $reservation) {
            try {
                $this->release($organizationId, (int) $reservation->id, "reservation-rollback-release:{$reservation->id}", meta: ['source' => 'rollout_rollback']);
                $processed++;
            } catch (ValidationException $exception) {
                $stillActive = InventoryReservation::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($reservation->id)
                    ->where('status', InventoryReservationStatus::Active->value)
                    ->exists();
                if ($stillActive) {
                    throw $exception;
                }
            }
        }

        return ['matched' => $matched, 'processed' => $processed];
    }

    /** @param array<string, mixed> $meta */
    private function transition(
        int $organizationId,
        int $reservationId,
        string $idempotencyKey,
        InventoryReservationStatus $target,
        InventoryReservationEventType $eventType,
        ?int $actorId,
        array $meta,
    ): InventoryReservation {
        $this->assertValidKey($idempotencyKey);
        $payloadHash = $this->transitionPayloadHash($organizationId, $reservationId, $eventType, $actorId, $meta);

        try {
            return DB::transaction(function () use ($organizationId, $reservationId, $idempotencyKey, $payloadHash, $target, $eventType, $actorId, $meta): InventoryReservation {
                $existingEvent = InventoryReservationEvent::query()
                    ->where('organization_id', $organizationId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existingEvent) {
                    return $this->validateTransitionReplay($existingEvent, $reservationId, $eventType, $payloadHash);
                }

                $reservation = InventoryReservation::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($reservationId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // El evento puede haber aparecido mientras esperabamos el lock de la reserva.
                $existingEvent = InventoryReservationEvent::query()
                    ->where('organization_id', $organizationId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existingEvent) {
                    return $this->validateTransitionReplay($existingEvent, $reservationId, $eventType, $payloadHash);
                }

                if ($reservation->status !== InventoryReservationStatus::Active) {
                    throw ValidationException::withMessages([
                        'reservation' => 'La reserva ya se encuentra en un estado terminal.',
                    ]);
                }
                if ($target === InventoryReservationStatus::Expired && (! $reservation->expires_at || $reservation->expires_at->isFuture())) {
                    throw ValidationException::withMessages([
                        'expires_at' => 'La reserva aun no ha vencido y no puede marcarse como expirada.',
                    ]);
                }

                $items = InventoryReservationItem::query()
                    ->where('organization_id', $organizationId)
                    ->where('reservation_id', $reservationId)
                    ->orderBy('inventory_balance_id')
                    ->get();
                $balances = $this->lockBalances(
                    $organizationId,
                    $items->pluck('inventory_balance_id')->map(fn ($id) => (int) $id)->all(),
                    requireActive: false,
                );
                $total = 0;

                foreach ($items as $item) {
                    /** @var InventoryBalance $balance */
                    $balance = $balances->get((int) $item->inventory_balance_id);
                    $quantity = (int) $item->quantity;
                    if ((int) $balance->reserved_stock < $quantity) {
                        throw new RuntimeException('El saldo reservado presenta una inconsistencia y no puede liberarse.');
                    }
                    $total += $quantity;
                    $balance->forceFill([
                        'reserved_stock' => (int) $balance->reserved_stock - $quantity,
                        'reservation_version' => (int) $balance->reservation_version + 1,
                    ])->save();
                }

                $terminalField = $target === InventoryReservationStatus::Released ? 'released_at' : 'expired_at';
                $reservation->forceFill([
                    'status' => $target->value,
                    $terminalField => now(),
                    'terminal_actor_id' => $actorId,
                ])->save();
                $this->recordEvent(
                    $reservation,
                    $idempotencyKey,
                    $payloadHash,
                    $eventType,
                    InventoryReservationStatus::Active,
                    $target,
                    -$total,
                    $actorId,
                    $meta,
                );

                return $reservation->fresh(['items', 'events']);
            }, $this->transactionAttempts());
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $existingEvent = InventoryReservationEvent::query()
                ->where('organization_id', $organizationId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if (! $existingEvent) {
                throw $exception;
            }

            return $this->validateTransitionReplay($existingEvent, $reservationId, $eventType, $payloadHash);
        }
    }

    /** @return array<int, int> */
    private function normalizeItems(InventoryReservationCommand $command): array
    {
        $this->assertValidKey($command->idempotencyKey);
        if ($command->organizationId < 1 || $command->items === []) {
            throw ValidationException::withMessages(['items' => 'La reserva requiere organizacion e items.']);
        }

        $normalized = [];
        foreach ($command->items as $item) {
            if ($item->balanceId < 1 || $item->quantity < 1) {
                throw ValidationException::withMessages(['items' => 'Cada item requiere saldo y cantidad positiva.']);
            }
            $normalized[$item->balanceId] = ($normalized[$item->balanceId] ?? 0) + $item->quantity;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /** @param array<int, int> $items */
    private function reservationPayloadHash(InventoryReservationCommand $command, array $items): string
    {
        $payload = [
            'organization_id' => $command->organizationId,
            'items' => $items,
            'expires_at' => $command->expiresAt ? CarbonImmutable::instance($command->expiresAt)->utc()->toIso8601String() : null,
            'source_type' => $command->sourceType,
            'source_id' => $command->sourceId,
            'source_code' => $command->sourceCode,
            'actor_id' => $command->actorId,
            'meta' => $command->meta,
        ];

        return $this->hash($payload);
    }

    /** @param array<string, mixed> $meta */
    private function transitionPayloadHash(int $organizationId, int $reservationId, InventoryReservationEventType $type, ?int $actorId, array $meta): string
    {
        return $this->hash([
            'organization_id' => $organizationId,
            'reservation_id' => $reservationId,
            'event_type' => $type->value,
            'actor_id' => $actorId,
            'meta' => $meta,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode(Arr::sortRecursive($payload), JSON_THROW_ON_ERROR));
    }

    private function resolvedExpiry(InventoryReservationCommand $command): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $expiry = $command->expiresAt
            ? CarbonImmutable::instance($command->expiresAt)
            : $now->addMinutes((int) config('catalog.reservations.default_ttl_minutes', 30));
        $maximum = $now->addMinutes((int) config('catalog.reservations.maximum_ttl_minutes', 1440));

        if ($expiry->lessThanOrEqualTo($now) || $expiry->greaterThan($maximum)) {
            throw ValidationException::withMessages([
                'expires_at' => 'La expiracion debe ser futura y respetar el TTL maximo configurado.',
            ]);
        }

        return $expiry;
    }

    /** @param array<int, int> $balanceIds */
    private function lockBalances(int $organizationId, array $balanceIds, bool $requireActive = true): Collection
    {
        sort($balanceIds, SORT_NUMERIC);
        $balances = InventoryBalance::query()
            ->where('organization_id', $organizationId)
            ->when($requireActive, fn ($builder) => $builder->where('is_active', true))
            ->whereIn('id', $balanceIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($balances->count() !== count($balanceIds)) {
            throw ValidationException::withMessages([
                'items' => $requireActive
                    ? 'Todos los saldos deben existir, estar activos y pertenecer a la organizacion.'
                    : 'Todos los saldos deben existir y pertenecer a la organizacion.',
            ]);
        }

        return $balances;
    }

    private function assertLedgerActive(int $organizationId): void
    {
        $mode = InventoryLedgerRollout::query()->where('organization_id', $organizationId)->first()?->mode;
        if ($mode !== InventoryLedgerRolloutMode::Active) {
            throw ValidationException::withMessages([
                'rollout' => 'Las reservas requieren el ledger de inventario en modo active.',
            ]);
        }
    }

    private function lockLedgerActive(int $organizationId): void
    {
        $mode = InventoryLedgerRollout::query()
            ->where('organization_id', $organizationId)
            ->sharedLock()
            ->first()?->mode;
        if ($mode !== InventoryLedgerRolloutMode::Active) {
            throw ValidationException::withMessages([
                'rollout' => 'Las reservas requieren el ledger de inventario en modo active.',
            ]);
        }
    }

    private function validateReservationReplay(InventoryReservation $reservation, string $payloadHash): InventoryReservation
    {
        if (! hash_equals((string) $reservation->payload_hash, $payloadHash)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave idempotente ya fue usada con un contenido diferente.',
            ]);
        }

        return $reservation->loadMissing('items', 'events');
    }

    private function validateTransitionReplay(InventoryReservationEvent $event, int $reservationId, InventoryReservationEventType $type, string $payloadHash): InventoryReservation
    {
        if ((int) $event->reservation_id !== $reservationId || $event->event_type !== $type || ! hash_equals((string) $event->payload_hash, $payloadHash)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave idempotente ya fue usada con un contenido diferente.',
            ]);
        }

        return InventoryReservation::query()
            ->where('organization_id', $event->organization_id)
            ->lockForUpdate()
            ->with('items', 'events')
            ->findOrFail($reservationId);
    }

    /** @param array<string, mixed> $meta */
    private function recordEvent(
        InventoryReservation $reservation,
        string $key,
        string $hash,
        InventoryReservationEventType $type,
        ?InventoryReservationStatus $before,
        InventoryReservationStatus $after,
        int $delta,
        ?int $actorId,
        array $meta,
    ): void {
        InventoryReservationEvent::query()->create([
            'organization_id' => $reservation->organization_id,
            'reservation_id' => $reservation->id,
            'idempotency_key' => $key,
            'payload_hash' => $hash,
            'event_type' => $type->value,
            'status_before' => $before?->value,
            'status_after' => $after->value,
            'quantity_delta' => $delta,
            'performed_by' => $actorId,
            'occurred_at' => now(),
            'meta' => $meta,
        ]);
    }

    private function assertValidKey(string $key): void
    {
        if ($key === '' || mb_strlen($key) > 160) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave idempotente es obligatoria y admite hasta 160 caracteres.']);
        }
    }

    private function transactionAttempts(): int
    {
        return max(1, (int) config('catalog.reservations.transaction_attempts', 5));
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }
}
