<?php

namespace Modules\Commerce\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasCapability extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_technical_core',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_technical_core' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(SaasPlan::class, 'saas_plan_capabilities', 'capability_id', 'plan_id')
            ->withTimestamps();
    }

    public function organizationEntitlements(): HasMany
    {
        return $this->hasMany(OrganizationEntitlement::class, 'capability_id');
    }
}
