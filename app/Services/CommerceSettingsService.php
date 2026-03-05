<?php

namespace App\Services;

use App\Models\CommerceSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CommerceSettingsService
{
    public const CACHE_KEY = 'commerce.settings.view';
    public const CACHE_TTL_SECONDS = 900;

    public function getForView(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => $this->build());
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
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
            $setting = CommerceSetting::query()->first();
        }

        $resolvedLogoUrl = $setting?->logo_path
            ? asset('storage/' . $setting->logo_path)
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
            'whatsapp_url' => $mobileDigits !== '' ? 'https://wa.me/' . $mobileDigits : null,
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
}
