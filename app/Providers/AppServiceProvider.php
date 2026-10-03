<?php

namespace App\Providers;

use App\Services\OrganizationContextService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Commerce\Services\CommerceSettingsService;
use Modules\Commerce\Services\StorefrontCartService;
use Modules\Commerce\Services\StorefrontRouteService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OrganizationContextService::class);
        $this->app->singleton(CommerceSettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('local')) {
            $appUrl = (string) config('app.url');

            if ($appUrl !== '') {
                URL::forceRootUrl($appUrl);

                if (str_starts_with($appUrl, 'https://')) {
                    URL::forceScheme('https');
                }
            }
        }

        View::composer('*', function (\Illuminate\View\View $view): void {
            if (request()->routeIs('home')) {
                return;
            }

            $organizationContext = $this->app->make(OrganizationContextService::class);

            $view->with('organizationContext', $organizationContext->forView());
            $view->with('commerce', $this->app->make(CommerceSettingsService::class)->getForView());
            $view->with('storefrontRoutes', $this->app->make(StorefrontRouteService::class));
            $view->with('storefrontCart', $this->app->make(StorefrontCartService::class));
        });
    }
}
