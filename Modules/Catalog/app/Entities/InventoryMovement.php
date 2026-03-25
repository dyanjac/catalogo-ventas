<?php

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Security\Models\SecurityBranch;

class InventoryMovement extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'product_id',
        'organization_id',
        'branch_id',
        'warehouse_id',
        'movement_type',
        'reason',
        'quantity',
        'stock_before',
        'stock_after',
        'average_cost_before',
        'unit_cost',
        'average_cost_after',
        'total_cost',
        'performed_by',
        'reference_type',
        'reference_id',
        'reference_code',
        'notes',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_before' => 'integer',
        'stock_after' => 'integer',
        'average_cost_before' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'average_cost_after' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'performed_by' => 'integer',
        'reference_id' => 'integer',
        'meta' => 'array',
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
