<?php

namespace Modules\Commerce\Services;

use App\Services\OrganizationContextService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Entities\CommerceSetting;

class CommerceSettingsService
{
    public const CACHE_TTL_SECONDS = 900;

    public function __construct(private readonly OrganizationContextService $organizationContext)
    {
    }

    public function getForView(): array
    {
        return Cache::remember($this->cacheKey(), self::CACHE_TTL_SECONDS, fn () => $this->build());
    }

    public function forgetCache(): void
    {
        Cache::forget($this->cacheKey());
        Cache::forget('commerce.settings.view.default');
    }

    private function build(): array
    {
        $fallbackName = (string) config('commerce.name', 'Name Company');
        $fallbackLogo = (string) config('commerce.logo', 'img/logo-V&V.png');
        $fallbackEmail = (string) config('commerce.email', '');
        $fallbackAddress = (string) config('commerce.address', '');
        $fallbackPhone = (string) config('commerce.phone', '');
        $fallbackMobile = (string) config('commerce.mobile', '');
        $fallbackTaxId = (string) config('commerce.tax_id', '');

        $setting = null;

        if (Schema::hasTable('commerce_settings')) {
            $setting = $this->currentSetting();
        }

        $resolvedLogoUrl = $setting?->logo_path
            ? asset('storage/'.$setting->logo_path)
            : $this->resolveLogoUrl($fallbackLogo);

        $resolvedPhone = $setting?->phone ?: $fallbackPhone;
        $resolvedMobile = $setting?->mobile ?: $fallbackMobile;
        $mobileDigits = preg_replace('/\D+/', '', $resolvedMobile);
        $phoneDigits = preg_replace('/\D+/', '', $resolvedPhone);

        return [
            'name' => $setting?->company_name ?: $fallbackName,
            'tax_id' => $setting?->tax_id ?: $fallbackTaxId,
            'address' => $setting?->address ?: $fallbackAddress,
            'phone' => $resolvedPhone,
            'phone_digits' => $phoneDigits,
            'mobile' => $resolvedMobile,
            'mobile_digits' => $mobileDigits,
            'email' => $setting?->email ?: $fallbackEmail,
            'logo_url' => $resolvedLogoUrl,
            'whatsapp_url' => $mobileDigits !== '' ? 'https://wa.me/'.$mobileDigits : null,
        ];
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

    private function cacheKey(): string
    {
        $organizationId = $this->organizationContext->currentOrganizationId();

        return 'commerce.settings.view.'.($organizationId ?: 'default');
    }

    private function currentSetting(): ?CommerceSetting
    {
        $query = CommerceSetting::query();

        if (! Schema::hasColumn('commerce_settings', 'organization_id')) {
            return $query->first();
        }

        $organizationId = $this->organizationContext->currentOrganizationId();

        if ($organizationId) {
            return $query->where('organization_id', $organizationId)->first()
                ?? CommerceSetting::query()->whereNull('organization_id')->first()
                ?? CommerceSetting::query()->first();
        }

        return $query->whereNull('organization_id')->first() ?? $query->first();
    }
}
