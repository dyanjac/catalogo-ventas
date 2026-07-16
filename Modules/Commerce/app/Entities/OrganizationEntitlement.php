<?php

namespace Modules\Commerce\Entities;

use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationEntitlement extends Model
{
    protected $fillable = [
        'organization_id',
        'capability_id',
        'state',
        'source',
        'starts_at',
        'ends_at',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function capability(): BelongsTo
    {
        return $this->belongsTo(SaasCapability::class, 'capability_id');
    }

    public function isActiveAt(CarbonInterface $at): bool
    {
        return (! $this->starts_at || $this->starts_at->lte($at))
            && (! $this->ends_at || $this->ends_at->gte($at));
    }
}
