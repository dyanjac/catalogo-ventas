<?php

namespace Modules\Commerce\Services;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationContextService;

class StorefrontRouteService
{
    public function __construct(
        private readonly OrganizationContextService $organizationContext,
        private readonly OrganizationEntitlementService $entitlements,
    ) {}

    public function homeForOrganization(?Organization $organization): ?string
    {
        if (! $organization?->isActiveStatus()
            || ! $organization->slug
            || ! $this->entitlements->hasCapability('sales.ecommerce', $organization)) {
            return null;
        }

        return route('ecommerce.home', ['commerce' => $organization->slug]);
    }

    public function homeForUser(?User $user): string
    {
        return $this->homeForOrganization($user?->organization) ?? route('home');
    }

    /**
     * @param  array<string,mixed>  $parameters
     */
    public function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        $storefront = $this->organizationContext->publicStorefront();

        if ($storefront) {
            return route(
                'ecommerce.'.$name,
                ['commerce' => $storefront->slug] + $parameters,
                $absolute
            );
        }

        return route($name, $parameters, $absolute);
    }

    public function matches(string $name): bool
    {
        $storefront = $this->organizationContext->publicStorefront();

        return request()->routeIs($storefront ? 'ecommerce.'.$name : $name);
    }
}
