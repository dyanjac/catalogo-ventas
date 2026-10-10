<?php

namespace Modules\Catalog\Entities;

use App\Casts\Quantity;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Security\Models\SecurityBranch;

class ProductBranchStock extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'product_id',
        'organization_id',
        'branch_id',
        'stock',
        'min_stock',
        'is_active',
    ];

    protected $casts = [
        'stock' => Quantity::class,
        'min_stock' => Quantity::class,
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
}
