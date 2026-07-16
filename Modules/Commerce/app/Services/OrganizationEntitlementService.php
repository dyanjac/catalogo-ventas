<?php

namespace Modules\Commerce\Services;

use App\Models\Organization;
use App\Services\OrganizationContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Entities\OrganizationEntitlement;
use Modules\Commerce\Entities\OrganizationPlanSubscription;
use Modules\Commerce\Entities\SaasCapability;
use Modules\Commerce\Entities\SaasPlan;

class OrganizationEntitlementService
{
    public function __construct(private readonly OrganizationContextService $organizationContext)
    {
    }

    public function hasCapability(string $capabilityCode, ?Organization $organization = null): bool
    {
        $organization ??= $this->organizationContext->current();

        if (! $organization || ! $this->schemaIsReady()) {
            return true;
        }

        $capabilities = $this->resolveCapabilities($organization);

        return $capabilities[$capabilityCode] ?? false;
    }

    public function hasModuleCapability(string $moduleCode, ?Organization $organization = null): bool
    {
        $capability = config("commerce.entitlements.module_capabilities.{$moduleCode}");

        return ! is_string($capability) || $capability === '' || $this->hasCapability($capability, $organization);
    }

    public function assignDefaultPlan(Organization $organization): ?OrganizationPlanSubscription
    {
        if (! $this->schemaIsReady()) {
            return null;
        }

        $plan = SaasPlan::query()->where('code', 'basic')->where('kind', 'plan')->where('is_active', true)->first();

        return $plan ? $this->assignPlan($organization, $plan, ['source' => 'organization_provisioning']) : null;
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public function assignPlan(Organization $organization, SaasPlan $plan, array $metadata = []): OrganizationPlanSubscription
    {
        if (! $this->schemaIsReady()) {
            throw ValidationException::withMessages(['plan' => 'El catálogo de entitlements aún no está disponible.']);
        }

        if ($plan->kind !== 'plan' || ! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => 'El plan seleccionado no está disponible para asignación.']);
        }

        return DB::transaction(function () use ($organization, $plan, $metadata): OrganizationPlanSubscription {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();

            OrganizationPlanSubscription::query()
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->whereHas('plan', fn ($query) => $query->where('kind', 'plan'))
                ->lockForUpdate()
                ->update(['status' => 'replaced', 'ends_at' => now(), 'updated_at' => now()]);

            $subscription = OrganizationPlanSubscription::query()->create([
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'metadata' => $metadata,
            ]);

            return $subscription;
        });
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public function activateAddon(Organization $organization, SaasPlan $addon, array $metadata = []): OrganizationPlanSubscription
    {
        if ($addon->kind !== 'addon' || ! $addon->is_active) {
            throw ValidationException::withMessages(['addon' => 'El addon seleccionado no está disponible para activación.']);
        }

        return DB::transaction(function () use ($organization, $addon, $metadata): OrganizationPlanSubscription {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();

            $subscription = OrganizationPlanSubscription::query()
                ->where('organization_id', $organization->id)
                ->where('plan_id', $addon->id)
                ->lockForUpdate()
                ->first()
                ?? new OrganizationPlanSubscription([
                    'organization_id' => $organization->id,
                    'plan_id' => $addon->id,
                ]);

            $subscription->fill([
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => null,
                'metadata' => $metadata,
            ])->save();

            return $subscription->fresh() ?? $subscription;
        });
    }

    public function deactivateAddon(Organization $organization, SaasPlan $addon): void
    {
        DB::transaction(function () use ($organization, $addon): void {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();

            OrganizationPlanSubscription::query()
                ->where('organization_id', $organization->id)
                ->where('plan_id', $addon->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'ends_at' => now(), 'updated_at' => now()]);
        });
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public function setOverride(
        Organization $organization,
        SaasCapability $capability,
        string $state,
        ?string $reason = null,
        array $metadata = []
    ): OrganizationEntitlement {
        if (! in_array($state, ['enabled', 'disabled'], true)) {
            throw ValidationException::withMessages(['state' => 'El estado debe ser enabled o disabled.']);
        }

        if ($capability->is_technical_core) {
            throw ValidationException::withMessages([
                'capability' => 'Las capacidades técnicas núcleo no se pueden desactivar por entitlement comercial.',
            ]);
        }

        return DB::transaction(function () use ($organization, $capability, $state, $reason, $metadata): OrganizationEntitlement {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();

            return OrganizationEntitlement::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'capability_id' => $capability->id],
                [
                    'state' => $state,
                    'source' => 'manual',
                    'starts_at' => now(),
                    'ends_at' => null,
                    'reason' => $reason,
                    'metadata' => $metadata,
                ]
            );
        });
    }

    /** @return array<string,bool> */
    private function resolveCapabilities(Organization $organization): array
    {
        $at = now();
        $resolved = SaasCapability::query()
            ->where('is_active', true)
            ->where('is_technical_core', true)
            ->pluck('code')
            ->mapWithKeys(fn (string $code): array => [$code => true])
            ->all();

        $subscriptions = OrganizationPlanSubscription::query()
            ->with('plan.capabilities')
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->get()
            ->filter(fn (OrganizationPlanSubscription $subscription): bool => $subscription->isActiveAt($at));

        foreach ($subscriptions as $subscription) {
            foreach ($subscription->plan->capabilities->where('is_active', true) as $capability) {
                $resolved[$capability->code] = true;
            }
        }

        $overrides = OrganizationEntitlement::query()
            ->with('capability')
            ->where('organization_id', $organization->id)
            ->get()
            ->filter(fn (OrganizationEntitlement $entitlement): bool => $entitlement->isActiveAt($at));

        foreach ($overrides as $override) {
            if (! $override->capability || ! $override->capability->is_active) {
                continue;
            }

            if (! $override->capability->is_technical_core) {
                $resolved[$override->capability->code] = $override->state === 'enabled';
            }
        }

        return $resolved;
    }

    private function schemaIsReady(): bool
    {
        return Schema::hasTable('saas_capabilities')
            && Schema::hasTable('saas_plans')
            && Schema::hasTable('organization_plan_subscriptions')
            && Schema::hasTable('organization_entitlements');
    }
}
