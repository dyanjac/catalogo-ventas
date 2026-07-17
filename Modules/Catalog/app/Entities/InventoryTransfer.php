<?php

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Enums\InventoryTransferStatus;
use Modules\Security\Models\SecurityBranch;

class InventoryTransfer extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'code',
        'idempotency_key',
        'payload_hash',
        'source_branch_id',
        'destination_branch_id',
        'source_warehouse_id',
        'destination_warehouse_id',
        'status',
        'created_by',
        'dispatched_at',
        'dispatched_by',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'notes',
    ];

    protected $casts = [
        'status' => InventoryTransferStatus::class,
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $transfer): void {
            if ($transfer->isDirty([
                'organization_id', 'code', 'idempotency_key', 'payload_hash', 'source_branch_id',
                'destination_branch_id', 'source_warehouse_id', 'destination_warehouse_id', 'created_by',
            ])) {
                throw new \LogicException('La identidad de una transferencia es inmutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Las transferencias de inventario no se eliminan.'));
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'source_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'destination_branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class, 'transfer_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'destination_warehouse_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(InventoryTransferEvent::class, 'transfer_id');
    }
}
