<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AuthenticationSettingsController extends Controller
{
    public function edit(): View
    {
        return view('security::settings.authentication');
    }
}
