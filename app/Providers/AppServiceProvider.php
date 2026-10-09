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
        $this->app->scoped(OrganizationContextService::class);
        $this->app->scoped(CommerceSettingsService::class);
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

        View::composer([
            'layouts.admin',
            'layouts.app',
            'layouts.app-home',
            'layouts.auth',
            'cart.view',
            'catalog.*',
            'contacto.index',
            'nosotros.index',
            'orders.show',
            'products.*',
            'storefront.home',
        ], function (\Illuminate\View\View $view): void {
            $organizationContext = $this->app->make(OrganizationContextService::class);

            $view->with('organizationContext', $organizationContext->forView());
            $view->with('commerce', $this->app->make(CommerceSettingsService::class)->getForView());
            $view->with('storefrontRoutes', $this->app->make(StorefrontRouteService::class));
            $view->with('storefrontCart', $this->app->make(StorefrontCartService::class));
        });
    }
}
