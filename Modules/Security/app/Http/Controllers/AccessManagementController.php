<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AccessManagementController extends Controller
{
    public function roles(): View
    {
        return view('security::settings.roles');
    }

    public function users(): View
    {
        return view('security::settings.users');
    }

    public function branches(): View
    {
        return view('security::settings.branches');
    }

    public function audit(): View
    {
        return view('security::settings.audit');
    }
}
