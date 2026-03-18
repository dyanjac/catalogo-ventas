<?php

namespace Modules\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Security\Services\SecurityAuthorizationService;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(protected SecurityAuthorizationService $authorization)
    {
    }

    public function handle(Request $request, Closure $next, string $permissionCode): Response
    {
        if (! $this->authorization->hasPermission($request->user(), $permissionCode)) {
            abort(403, 'No tienes permiso para '.$permissionCode.'.');
        }

        return $next($request);
    }
}
