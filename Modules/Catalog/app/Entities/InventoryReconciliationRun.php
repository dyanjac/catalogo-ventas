<?php

declare(strict_types=1);

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryReconciliationRun extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'status',
        'checked_balances',
        'issue_count',
        'started_at',
        'finished_at',
        'summary',
    ];

    protected $casts = [
        'checked_balances' => 'integer',
        'issue_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'summary' => 'array',
    ];

    public function issues(): HasMany
    {
        return $this->hasMany(InventoryReconciliationIssue::class, 'run_id');
    }
}
