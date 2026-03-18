<?php

namespace Modules\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Security\Services\SecurityAuthorizationService;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function __construct(protected SecurityAuthorizationService $authorization)
    {
    }

    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        if (! $this->authorization->canAccessModule($request->user(), $moduleCode)) {
            abort(403, 'No tienes acceso al modulo '.$moduleCode.'.');
        }

        return $next($request);
    }
}
