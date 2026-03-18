<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Security\Services\SecurityAuthorizationService;

class AdminLoginController extends Controller
{
    public function create(SecurityAuthorizationService $authorization): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended(
                $authorization->canAccessAdminPanel(Auth::user()) ? route('admin.dashboard') : route('home')
            );
        }

        return view('security::auth.admin-login');
    }
}
