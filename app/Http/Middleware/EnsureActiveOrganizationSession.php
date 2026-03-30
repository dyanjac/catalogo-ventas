<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Security\Services\SecurityAuditService;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveOrganizationSession
{
    public function __construct(private readonly SecurityAuditService $auditService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $organization = $user?->organization;

        if (! $organization?->isSuspended()) {
            return $next($request);
        }

        $this->auditService->log(
            eventType: 'authentication',
            eventCode: 'security.session.suspended_tenant_blocked',
            result: 'warning',
            message: 'Se bloqueó una sesión porque la organización está suspendida.',
            actor: $user,
            target: $user,
            module: 'security',
            context: [
                'organization_id' => $organization->id,
                'organization_code' => $organization->code,
                'path' => $request->path(),
            ],
        );

        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $redirect = $request->is('admin') || $request->is('admin/*')
            ? route('admin.login')
            : route('login');

        return redirect($redirect)->with('error', 'La organización seleccionada está suspendida. El acceso fue bloqueado hasta su reactivación.');
    }
}
