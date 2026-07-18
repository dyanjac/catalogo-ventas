<?php

namespace Modules\Operations\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Operations\Http\Controllers\ReadinessController;
use Modules\Operations\Http\Middleware\AttachObservabilityContext;

final class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::middleware(AttachObservabilityContext::class)
            ->get('/health/ready', ReadinessController::class)
            ->name('health.ready');
        Route::middleware('web')->group(module_path('Operations', '/routes/web.php'));
    }
}
