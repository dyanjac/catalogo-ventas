<?php

declare(strict_types=1);

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Enums\InventoryLocationType;
use Modules\Security\Models\SecurityBranch;

class InventoryBalance extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'product_id',
        'branch_id',
        'warehouse_id',
        'location_type',
        'location_key',
        'physical_stock',
        'reserved_stock',
        'in_transit_stock',
        'min_stock',
        'average_cost',
        'last_cost',
        'version',
        'is_active',
    ];

    protected $casts = [
        'location_type' => InventoryLocationType::class,
        'physical_stock' => 'integer',
        'reserved_stock' => 'integer',
        'in_transit_stock' => 'integer',
        'min_stock' => 'integer',
        'average_cost' => 'decimal:4',
        'last_cost' => 'decimal:4',
        'version' => 'integer',
        'is_active' => 'boolean',
    ];

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

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public static function locationKey(int $branchId, ?int $warehouseId): string
    {
        return $warehouseId ? 'warehouse:'.$warehouseId : 'unallocated:'.$branchId;
    }
}
