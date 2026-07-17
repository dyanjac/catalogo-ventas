<?php

declare(strict_types=1);

namespace Modules\Transport\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Transport\Services\TransportGuideProviderResolver;

class TransportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/config.php', 'transport');
        $this->app->singleton(TransportGuideProviderResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Transport', 'database/migrations'));
        $this->loadViewsFrom(module_path('Transport', 'resources/views'), 'transport');
        $this->loadRoutesFrom(module_path('Transport', 'routes/web.php'));
    }
}
