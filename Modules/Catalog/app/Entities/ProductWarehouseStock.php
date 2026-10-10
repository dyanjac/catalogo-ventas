<?php

namespace Modules\Catalog\Entities;

use App\Casts\Quantity;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Security\Models\SecurityBranch;

class ProductWarehouseStock extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'product_id',
        'organization_id',
        'branch_id',
        'warehouse_id',
        'stock',
        'min_stock',
        'average_cost',
        'last_cost',
        'is_active',
    ];

    protected $casts = [
        'stock' => Quantity::class,
        'min_stock' => Quantity::class,
        'average_cost' => 'decimal:6',
        'last_cost' => 'decimal:6',
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
}
