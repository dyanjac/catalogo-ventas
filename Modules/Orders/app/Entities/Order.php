<?php

namespace Modules\Orders\Entities;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Security\Models\SecurityBranch;

class Order extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'user_id',
        'organization_id',
        'branch_id',
        'series',
        'order_number',
        'status',
        'currency',
        'subtotal',
        'discount',
        'shipping',
        'tax',
        'total',
        'shipping_address',
        'payment_method',
        'payment_status',
        'paid_at',
        'transaction_id',
        'observations',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
