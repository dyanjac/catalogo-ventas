<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Subscriptions\Console\DispatchAccrualsCommand;
use Modules\Subscriptions\Console\RenewSubscriptionsCommand;

class SubscriptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/config.php', 'subscriptions');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Subscriptions', 'database/migrations'));
        $this->loadViewsFrom(module_path('Subscriptions', 'resources/views'), 'subscriptions');
        $this->loadRoutesFrom(module_path('Subscriptions', 'routes/web.php'));
        if ($this->app->runningInConsole()) {
            $this->commands([DispatchAccrualsCommand::class, RenewSubscriptionsCommand::class]);
        }
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('subscriptions:accruals:dispatch')->everyFiveMinutes()->timezone('UTC')->withoutOverlapping(10)->onOneServer();
            $schedule->command('subscriptions:renewals:dispatch')->hourly()->timezone('UTC')->withoutOverlapping(10)->onOneServer();
        });
    }
}
