<?php

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransferItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'transfer_id',
        'organization_id',
        'product_id',
        'source_balance_id',
        'destination_balance_id',
        'quantity',
        'dispatched_quantity',
        'received_quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'dispatched_quantity' => 'integer',
        'received_quantity' => 'integer',
        'unit_cost' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $item): void {
            if ($item->isDirty(['organization_id', 'transfer_id', 'product_id', 'source_balance_id', 'destination_balance_id', 'quantity'])) {
                throw new \LogicException('La identidad y cantidad solicitada de un item de transferencia son inmutables.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Los items de transferencia no se eliminan.'));
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceBalance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class, 'source_balance_id');
    }

    public function destinationBalance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class, 'destination_balance_id');
    }
}
