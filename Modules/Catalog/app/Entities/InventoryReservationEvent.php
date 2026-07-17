<?php

declare(strict_types=1);

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Modules\Catalog\Enums\InventoryReservationEventType;
use Modules\Catalog\Enums\InventoryReservationStatus;

class InventoryReservationEvent extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'reservation_id', 'idempotency_key', 'payload_hash', 'event_type',
        'status_before', 'status_after', 'quantity_delta', 'performed_by', 'occurred_at', 'meta',
    ];

    protected $casts = [
        'event_type' => InventoryReservationEventType::class,
        'status_before' => InventoryReservationStatus::class,
        'status_after' => InventoryReservationStatus::class,
        'quantity_delta' => 'integer',
        'performed_by' => 'integer',
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los eventos de reserva son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los eventos de reserva son inmutables.'));
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class, 'reservation_id');
    }
}
