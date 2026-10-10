<?php

namespace Modules\Catalog\Entities;

use App\Casts\Quantity;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Modules\Catalog\Enums\InventoryMovementType;
use Modules\Security\Models\SecurityBranch;

class InventoryMovement extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'product_id',
        'organization_id',
        'inventory_balance_id',
        'branch_id',
        'warehouse_id',
        'movement_type',
        'idempotency_key',
        'payload_hash',
        'reason',
        'reason_code',
        'quantity',
        'stock_before',
        'stock_after',
        'balance_version',
        'average_cost_before',
        'unit_cost',
        'average_cost_after',
        'total_cost',
        'performed_by',
        'reference_type',
        'reference_id',
        'reversal_of_id',
        'ledger_generation',
        'occurred_at',
        'reference_code',
        'notes',
        'meta',
    ];

    protected $casts = [
        'movement_type' => InventoryMovementType::class,
        'quantity' => Quantity::class,
        'stock_before' => Quantity::class,
        'stock_after' => Quantity::class,
        'average_cost_before' => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'average_cost_after' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'performed_by' => 'integer',
        'reference_id' => 'integer',
        'balance_version' => 'integer',
        'ledger_generation' => 'integer',
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de inventario son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de inventario son inmutables.'));
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class, 'inventory_balance_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'branch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'warehouse_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
