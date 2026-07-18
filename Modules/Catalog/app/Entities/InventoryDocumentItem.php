<?php

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDocumentItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'document_id',
        'organization_id',
        'product_id',
        'inventory_balance_id',
        'inventory_movement_id',
        'quantity',
        'target_quantity',
        'unit_cost',
        'line_total',
        'notes',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'target_quantity' => 'integer',
        'unit_cost' => 'decimal:4',
        'line_total' => 'decimal:4',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        $guard = function (self $item): void {
            if ($item->document()->where('status', 'confirmed')->exists()) {
                throw new \LogicException('Los items de un documento confirmado son inmutables.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'document_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class, 'inventory_balance_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }
}
