<?php

namespace Modules\Commerce\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationCapability
{
    public function __construct(private readonly OrganizationEntitlementService $entitlements)
    {
    }

    public function handle(Request $request, Closure $next, string $capabilityCode): Response
    {
        if (! $this->entitlements->hasCapability($capabilityCode)) {
            abort(403, "La organización actual no tiene contratada la capacidad {$capabilityCode}.");
        }

        return $next($request);
    }
}
