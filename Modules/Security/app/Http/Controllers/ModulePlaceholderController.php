<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Security\Models\SecurityModule;
use Modules\Security\Services\SecurityAuthorizationService;

class ModulePlaceholderController extends Controller
{
    public function show(Request $request, SecurityModule $module, SecurityAuthorizationService $authorization): View
    {
        abort_unless($authorization->canAccessModule($request->user(), $module->code), 403);

        return view('security::settings.module-placeholder', [
            'module' => $module,
        ]);
    }
}
