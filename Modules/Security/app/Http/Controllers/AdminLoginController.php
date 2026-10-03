<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Commerce\Services\StorefrontRouteService;
use Modules\Security\Services\SecurityAuthorizationService;

class AdminLoginController extends Controller
{
    public function create(SecurityAuthorizationService $authorization, StorefrontRouteService $storefrontRoutes): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended(
                $authorization->canAccessAdminPanel(Auth::user())
                    ? route('admin.dashboard')
                    : $storefrontRoutes->homeForUser(Auth::user())
            );
        }

        return view('security::auth.admin-login');
    }
}
