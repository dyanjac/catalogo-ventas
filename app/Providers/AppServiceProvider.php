<?php

namespace App\Providers;

use App\Models\CommerceSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        $fallbackName = (string) config('commerce.name', 'Name Company');
        $fallbackLogo = (string) config('commerce.logo', 'img/logo-V&V.png');
        $fallbackEmail = (string) config('commerce.email', '');
        $fallbackAddress = (string) config('commerce.address', '');
        $fallbackPhone = (string) config('commerce.phone', '');
        $fallbackMobile = (string) config('commerce.mobile', '');
        $fallbackTaxId = (string) config('commerce.tax_id', '');

        $setting = null;

        if (Schema::hasTable('commerce_settings')) {
            $setting = CommerceSetting::query()->first();
        }

        $resolvedLogoUrl = $setting?->logo_path
            ? asset('storage/' . $setting->logo_path)
            : $this->resolveLogoUrl($fallbackLogo);

        View::share('commerce', [
            'name' => $setting?->company_name ?: $fallbackName,
            'tax_id' => $setting?->tax_id ?: $fallbackTaxId,
            'address' => $setting?->address ?: $fallbackAddress,
            'phone' => $setting?->phone ?: $fallbackPhone,
            'mobile' => $setting?->mobile ?: $fallbackMobile,
            'email' => $setting?->email ?: $fallbackEmail,
            'logo_url' => $resolvedLogoUrl,
        ]);
    }

    private function resolveLogoUrl(string $logo): string
    {
        if ($logo === '') {
            return asset('img/logo-V&V.png');
        }

        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return $logo;
        }

        return asset(ltrim($logo, '/'));
    }
}
