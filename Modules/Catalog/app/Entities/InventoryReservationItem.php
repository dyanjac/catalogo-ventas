<?php

declare(strict_types=1);

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryReservationItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'reservation_id', 'inventory_balance_id', 'product_id', 'branch_id', 'warehouse_id', 'quantity',
    ];

    protected $casts = ['quantity' => 'integer'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los items de reserva son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los items de reserva son inmutables.'));
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class, 'reservation_id');
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class, 'inventory_balance_id');
    }
}
