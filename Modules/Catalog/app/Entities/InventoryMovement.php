<?php

namespace Modules\Catalog\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Security\Models\SecurityBranch;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id',
        'branch_id',
        'movement_type',
        'reason',
        'quantity',
        'stock_before',
        'stock_after',
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
