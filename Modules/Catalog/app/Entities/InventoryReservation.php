<?php

declare(strict_types=1);

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Modules\Catalog\Enums\InventoryReservationStatus;

class InventoryReservation extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'idempotency_key', 'payload_hash', 'status', 'source_type', 'source_id',
        'source_code', 'expires_at', 'released_at', 'expired_at', 'consumed_at', 'created_by', 'terminal_actor_id', 'meta',
    ];

    protected $casts = [
        'status' => InventoryReservationStatus::class,
        'source_id' => 'integer',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
        'expired_at' => 'datetime',
        'consumed_at' => 'datetime',
        'created_by' => 'integer',
        'terminal_actor_id' => 'integer',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Las reservas de inventario no se eliminan.'));
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryReservationItem::class, 'reservation_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(InventoryReservationEvent::class, 'reservation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
