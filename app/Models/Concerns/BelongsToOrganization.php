<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Services\OrganizationContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::creating(function ($model): void {
            if (! Schema::hasColumn($model->getTable(), 'organization_id')) {
                return;
            }

            if ($model->getAttribute('organization_id')) {
                return;
            }

            $organizationId = app(OrganizationContextService::class)->currentOrganizationId();

            if ($organizationId) {
                $model->setAttribute('organization_id', $organizationId);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForCurrentOrganization(Builder $query): Builder
    {
        $organizationId = app(OrganizationContextService::class)->currentOrganizationId();

        if (! $organizationId) {
            return $query;
        }

        return $query->where($this->qualifyColumn('organization_id'), $organizationId);
    }
}
