<?php

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Enums\InventoryDocumentStatus;
use Modules\Catalog\Enums\InventoryDocumentType;
use Modules\Security\Models\SecurityBranch;

class InventoryDocument extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'code',
        'idempotency_key',
        'payload_hash',
        'document_type',
        'status',
        'organization_id',
        'branch_id',
        'warehouse_id',
        'reservation_id',
        'reversal_of_id',
        'reason',
        'external_reference',
        'issued_at',
        'confirmed_at',
        'created_by',
        'confirmed_by',
        'notes',
        'meta',
    ];

    protected $casts = [
        'document_type' => InventoryDocumentType::class,
        'status' => InventoryDocumentStatus::class,
        'issued_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'created_by' => 'integer',
        'confirmed_by' => 'integer',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            if ($document->getRawOriginal('status') === InventoryDocumentStatus::Confirmed->value) {
                throw new \LogicException('Un documento de inventario confirmado es inmutable.');
            }
        });
        static::deleting(function (self $document): void {
            if ($document->status === InventoryDocumentStatus::Confirmed) {
                throw new \LogicException('Un documento de inventario confirmado no se puede eliminar.');
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'branch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryDocumentItem::class, 'document_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class, 'reservation_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
