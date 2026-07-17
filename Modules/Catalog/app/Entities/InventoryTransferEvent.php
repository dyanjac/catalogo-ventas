<?php

declare(strict_types=1);

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Modules\Catalog\Enums\InventoryTransferEventType;
use Modules\Catalog\Enums\InventoryTransferStatus;

class InventoryTransferEvent extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'transfer_id', 'idempotency_key', 'payload_hash', 'event_type',
        'status_before', 'status_after', 'performed_by', 'occurred_at', 'notes', 'meta',
    ];

    protected $casts = [
        'event_type' => InventoryTransferEventType::class,
        'status_before' => InventoryTransferStatus::class,
        'status_after' => InventoryTransferStatus::class,
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los eventos de transferencia son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los eventos de transferencia son inmutables.'));
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'transfer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferEventItem::class, 'event_id');
    }
}
