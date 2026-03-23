<?php

namespace Modules\Catalog\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Security\Models\SecurityBranch;

class InventoryWarehouse extends Model
{
    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'branch_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductWarehouseStock::class, 'warehouse_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(InventoryDocument::class, 'warehouse_id');
    }
}
