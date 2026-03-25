<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'year',
        'month',
        'starts_at',
        'ends_at',
        'status',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'closed_at' => 'datetime',
    ];

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $field ??= $this->getRouteKeyName();

        return $query->forCurrentOrganization()->where($field, $value);
    }
}
