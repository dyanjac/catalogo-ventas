<?php

namespace Modules\Security\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Security\Models\SecurityAuthSetting;

class SecurityAuthSettingsService
{
    public function defaults(): array
    {
        return config('security.auth', []);
    }

    public function getForView(): array
    {
        $defaults = $this->defaults();

        if (! Schema::hasTable('security_auth_settings')) {
            return $defaults;
        }

        $setting = SecurityAuthSetting::query()->first();

        if (! $setting) {
            return $defaults;
        }

        return array_merge($defaults, array_filter(
            $setting->only(array_keys($defaults)),
            fn ($value) => $value !== null
        ));
    }

    public function update(array $data): array
    {
        $defaults = $this->defaults();
        $payload = [];

        foreach ($defaults as $key => $default) {
            $payload[$key] = $this->normalizeValue($key, $data[$key] ?? $default);
        }

        if (! Schema::hasTable('security_auth_settings')) {
            return array_merge($defaults, $payload);
        }

        $setting = SecurityAuthSetting::query()->firstOrNew(['id' => 1]);
        $setting->fill($payload);
        $setting->save();

        return array_merge($defaults, $setting->only(array_keys($defaults)));
    }

    private function normalizeValue(string $key, mixed $value): mixed
    {
        $booleanKeys = [
            'sso_enabled',
            'hide_internal_prompt',
            'auto_user_provisioning',
            'oauth_auto_team_membership',
            'oauth_google_enabled',
            'oauth_github_enabled',
            'oauth_custom_enabled',
            'ldap_enabled',
            'ldap_anonymous',
            'ldap_use_starttls',
            'ldap_use_tls',
            'ldap_verify_certificate',
            'ldap_assign_admin_by_group',
        ];

        $integerKeys = [
            'session_lifetime_hours',
            'password_min_length',
            'ldap_port',
        ];

        if (in_array($key, $booleanKeys, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        if (in_array($key, $integerKeys, true)) {
            return (int) $value;
        }

        return $value;
    }
}
