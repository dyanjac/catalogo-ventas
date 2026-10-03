<?php

namespace Modules\Commerce\Services;

use App\Services\OrganizationContextService;

class StorefrontRouteService
{
    public function __construct(private readonly OrganizationContextService $organizationContext) {}

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
