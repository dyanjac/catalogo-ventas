<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'level',
        'is_active',
        'is_default_sales',
        'is_default_purchase',
        'is_default_tax',
        'is_default_receivable',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default_sales' => 'boolean',
        'is_default_purchase' => 'boolean',
        'is_default_tax' => 'boolean',
        'is_default_receivable' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
