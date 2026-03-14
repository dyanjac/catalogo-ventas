<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended(
                Auth::user()?->isSuperAdmin() ? route('admin.dashboard') : route('home')
            );
        }

        return view('security::auth.admin-login');
    }
}
