<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccountingCostCenter extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $field ??= $this->getRouteKeyName();

        return $query->forCurrentOrganization()->where($field, $value);
    }
}
