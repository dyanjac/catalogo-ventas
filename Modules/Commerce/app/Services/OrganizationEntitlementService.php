<?php

namespace Modules\Commerce\Services;

use App\Models\Organization;
use App\Services\OrganizationContextService;
use App\Services\SharedNavigationCache;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Entities\OrganizationEntitlement;
use Modules\Commerce\Entities\OrganizationPlanSubscription;
use Modules\Commerce\Entities\SaasCapability;
use Modules\Commerce\Entities\SaasPlan;

class OrganizationEntitlementService
{
    /** @var array<int,array<string,bool>> */
    private array $capabilitiesByOrganization = [];

    /** @var array<int,int> */
    private array $organizationRevisions = [];

    private ?bool $schemaReady = null;

    public function __construct(
        private readonly OrganizationContextService $organizationContext,
        private readonly SharedNavigationCache $sharedCache
    ) {}

    public function hasCapability(
        string $capabilityCode,
        ?Organization $organization = null,
        bool $fresh = false
    ): bool {
        $organization ??= $this->organizationContext->current();

        if (! $organization || ! $this->schemaIsReady()) {
            return true;
        }

        if ($fresh) {
            unset($this->capabilitiesByOrganization[$organization->id]);
        }

        $capabilities = $this->resolveCapabilities($organization, $fresh);

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
     * @param  array<string,mixed>  $metadata
     */
    public function assignPlan(Organization $organization, SaasPlan $plan, array $metadata = []): OrganizationPlanSubscription
    {
        if (! $this->schemaIsReady()) {
            throw ValidationException::withMessages(['plan' => 'El catálogo de entitlements aún no está disponible.']);
        }

        if ($plan->kind !== 'plan' || ! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => 'El plan seleccionado no está disponible para asignación.']);
        }

        $subscription = DB::transaction(function () use ($organization, $plan, $metadata): OrganizationPlanSubscription {
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

        $this->forgetOrganization($organization);

        return $subscription;
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    public function activateAddon(Organization $organization, SaasPlan $addon, array $metadata = []): OrganizationPlanSubscription
    {
        if ($addon->kind !== 'addon' || ! $addon->is_active) {
            throw ValidationException::withMessages(['addon' => 'El addon seleccionado no está disponible para activación.']);
        }

        $subscription = DB::transaction(function () use ($organization, $addon, $metadata): OrganizationPlanSubscription {
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

        $this->forgetOrganization($organization);

        return $subscription;
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

        $this->forgetOrganization($organization);
    }

    /**
     * @param  array<string,mixed>  $metadata
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

        $entitlement = DB::transaction(function () use ($organization, $capability, $state, $reason, $metadata): OrganizationEntitlement {
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

        $this->forgetOrganization($organization);

        return $entitlement;
    }

    public function forgetOrganization(Organization|int $organization): void
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        unset($this->capabilitiesByOrganization[$organizationId]);
        $this->organizationRevisions[$organizationId] = ($this->organizationRevisions[$organizationId] ?? 0) + 1;
        $this->sharedCache->flushTag($this->organizationCacheTag($organizationId));
    }

    public function revision(Organization|int $organization): int
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $this->organizationRevisions[$organizationId] ?? 0;
    }

    /** @return array<string,bool> */
    private function resolveCapabilities(Organization $organization, bool $fresh = false): array
    {
        if (! $fresh && isset($this->capabilitiesByOrganization[$organization->id])) {
            return $this->capabilitiesByOrganization[$organization->id];
        }

        $cacheTags = $this->cacheTags($organization->id);

        if (! $fresh) {
            $cached = $this->sharedCache->get($cacheTags, 'capabilities');

            if (is_array($cached)) {
                return $this->capabilitiesByOrganization[$organization->id] = $cached;
            }
        }

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

        $this->sharedCache->put(
            $cacheTags,
            'capabilities',
            $resolved,
            $this->cacheTtlFor($at, $subscriptions, $overrides)
        );

        return $this->capabilitiesByOrganization[$organization->id] = $resolved;
    }

    /**
     * @param  Collection<int,OrganizationPlanSubscription>  $subscriptions
     * @param  Collection<int,OrganizationEntitlement>  $overrides
     */
    private function cacheTtlFor(CarbonInterface $at, Collection $subscriptions, Collection $overrides): int
    {
        $ttl = $this->sharedCache->ttlSeconds();

        foreach ($subscriptions->concat($overrides) as $temporalModel) {
            foreach ([$temporalModel->starts_at, $temporalModel->ends_at] as $boundary) {
                if ($boundary?->gt($at)) {
                    $ttl = min($ttl, max(1, $boundary->timestamp - $at->timestamp + 1));
                }
            }
        }

        return $ttl;
    }

    /** @return array<int,string> */
    private function cacheTags(int $organizationId): array
    {
        return [
            'navigation-response',
            $this->organizationCacheTag($organizationId),
            'navigation-response:entitlements',
        ];
    }

    private function organizationCacheTag(int $organizationId): string
    {
        return "navigation-response:organization:{$organizationId}";
    }

    private function schemaIsReady(): bool
    {
        if (! config('commerce.entitlements.schema_checks_enabled', true)) {
            return true;
        }

        return $this->schemaReady ??= Schema::hasTable('saas_capabilities')
            && Schema::hasTable('saas_plans')
            && Schema::hasTable('organization_plan_subscriptions')
            && Schema::hasTable('organization_entitlements');
    }
}
