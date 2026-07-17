<?php

declare(strict_types=1);

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryTransferEventItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'event_id', 'transfer_item_id', 'quantity', 'transit_delta', 'inventory_movement_id',
    ];

    protected $casts = ['quantity' => 'integer', 'transit_delta' => 'integer'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los items de evento de transferencia son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los items de evento de transferencia son inmutables.'));
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(InventoryTransferEvent::class, 'event_id');
    }
}
