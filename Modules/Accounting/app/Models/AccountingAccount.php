<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
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
        'organization_id' => 'integer',
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

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $field ??= $this->getRouteKeyName();

        return $query->forCurrentOrganization()->where($field, $value);
    }
}
