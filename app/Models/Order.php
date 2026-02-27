<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'user_id',
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

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
}
