<?php

namespace Modules\Commerce\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasPlan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'kind',
        'description',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(SaasCapability::class, 'saas_plan_capabilities', 'plan_id', 'capability_id')
            ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganizationPlanSubscription::class, 'plan_id');
    }

    public function isAddon(): bool
    {
        return $this->kind === 'addon';
    }
}
