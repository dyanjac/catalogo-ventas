<?php

namespace Modules\AdminTheme\Services;

use Illuminate\Support\Facades\Cache;
use Modules\AdminTheme\Models\AdminThemeSetting;

class AdminThemePaletteService
{
    private const CACHE_KEY = 'admin_theme_palette_v1';

    public function getPalette(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $defaults = config('admintheme.defaults', []);
            $setting = AdminThemeSetting::query()->latest('id')->first();

            if (! $setting) {
                return $defaults;
            }

            return array_merge($defaults, array_filter(
                $setting->only(array_keys($defaults)),
                fn ($value) => is_string($value) && trim($value) !== ''
            ));
        });
    }

    public function updatePalette(array $data): void
    {
        $defaults = config('admintheme.defaults', []);
        $payload = [];

        foreach (array_keys($defaults) as $key) {
            $payload[$key] = $this->normalizeColor($data[$key] ?? null, $defaults[$key]);
        }

        AdminThemeSetting::query()->updateOrCreate(['id' => 1], $payload);
        Cache::forget(self::CACHE_KEY);
    }

    private function normalizeColor(mixed $value, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $value = strtoupper(trim($value));

        if (! preg_match('/^#[0-9A-F]{6}$/', $value)) {
            return $fallback;
        }

        return $value;
    }
}
