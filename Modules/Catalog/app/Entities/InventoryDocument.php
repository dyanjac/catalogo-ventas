<?php

namespace Modules\Catalog\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Security\Models\SecurityBranch;

class InventoryDocument extends Model
{
    protected $fillable = [
        'code',
        'document_type',
        'status',
        'branch_id',
        'warehouse_id',
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
        'issued_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'created_by' => 'integer',
        'confirmed_by' => 'integer',
        'meta' => 'array',
    ];

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
}
