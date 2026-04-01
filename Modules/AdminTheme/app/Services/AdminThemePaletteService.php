<?php

namespace Modules\AdminTheme\Services;

use App\Services\OrganizationContextService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\AdminTheme\Models\AdminThemeSetting;

class AdminThemePaletteService
{
    public function __construct(
        private readonly OrganizationContextService $organizationContext
    ) {}

    public function getPalette(): array
    {
        return $this->paletteForOrganization($this->organizationContext->currentOrganizationId());
    }

    public function getAuthPalette(): array
    {
        if (auth()->check()) {
            return $this->getPalette();
        }

        $explicitOrganization = $this->organizationContext->explicit();

        if (! $explicitOrganization) {
            return config('admintheme.defaults', []);
        }

        return $this->paletteForOrganization($explicitOrganization->id);
    }

    public function updatePalette(array $data): void
    {
        $defaults = config('admintheme.defaults', []);
        $organizationId = $this->organizationContext->currentOrganizationId();
        $payload = [];

        foreach (array_keys($defaults) as $key) {
            $payload[$key] = $this->normalizeColor($data[$key] ?? null, $defaults[$key]);
        }

        if ($organizationId && $this->supportsOrganizationScope()) {
            $payload['organization_id'] = $organizationId;
        }

        if ($this->supportsOrganizationScope()) {
            AdminThemeSetting::query()->updateOrCreate(
                ['organization_id' => $organizationId],
                $payload
            );
        } else {
            AdminThemeSetting::query()->updateOrCreate(
                ['id' => 1],
                $payload
            );
        }

        Cache::forget($this->cacheKey($organizationId));
    }

    public function resetPalette(): void
    {
        $organizationId = $this->organizationContext->currentOrganizationId();

        if ($organizationId && $this->supportsOrganizationScope()) {
            AdminThemeSetting::query()
                ->where('organization_id', $organizationId)
                ->delete();
        } else {
            AdminThemeSetting::query()
                ->whereKey(1)
                ->delete();
        }

        Cache::forget($this->cacheKey($organizationId));
    }

    private function paletteForOrganization(?int $organizationId): array
    {
        $defaults = config('admintheme.defaults', []);

        if (! $organizationId) {
            return $defaults;
        }

        return Cache::rememberForever($this->cacheKey($organizationId), function () use ($defaults, $organizationId) {
            $setting = $this->supportsOrganizationScope()
                ? AdminThemeSetting::query()
                    ->where('organization_id', $organizationId)
                    ->latest('id')
                    ->first()
                : AdminThemeSetting::query()->latest('id')->first();

            if (! $setting) {
                return $defaults;
            }

            return array_merge($defaults, array_filter(
                $setting->only(array_keys($defaults)),
                fn ($value) => is_string($value) && trim($value) !== ''
            ));
        });
    }

    private function cacheKey(?int $organizationId): string
    {
        return 'admin_theme_palette_v1:' . ($organizationId ?: 'default');
    }

    private function supportsOrganizationScope(): bool
    {
        static $supportsOrganizationScope;

        return $supportsOrganizationScope ??= Schema::hasColumn('admin_theme_settings', 'organization_id');
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
