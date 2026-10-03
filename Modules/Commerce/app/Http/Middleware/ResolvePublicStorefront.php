<?php

namespace Modules\Commerce\Http\Middleware;

use App\Models\Organization;
use App\Services\OrganizationContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicStorefront
{
    public function __construct(private readonly OrganizationEntitlementService $entitlements) {}

    public function handle(Request $request, Closure $next, string $parameter = 'commerce'): Response
    {
        $value = $request->route($parameter);
        $organization = $value instanceof Organization
            ? $value
            : Organization::query()
                ->where('slug', Str::slug((string) $value))
                ->first();

        abort_unless(
            $organization?->isActiveStatus()
                && $this->entitlements->hasCapability('sales.ecommerce', $organization),
            404
        );

        $request->attributes->set(OrganizationContextService::PUBLIC_STOREFRONT_ATTRIBUTE, $organization);

        return $next($request);
    }
}
