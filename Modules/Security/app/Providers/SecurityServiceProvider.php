<?php

namespace Modules\Security\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use LdapRecord\Models\OpenLDAP\User as LdapOpenLdapUser;
use Modules\Security\Services\SecurityAuthSettingsService;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SecurityServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Security';

    protected string $nameLower = 'security';

    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->configureLdapRuntime();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    public function register(): void
    {
        $this->app->singleton(SecurityAuthSettingsService::class);
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerCommands(): void
    {
    }

    protected function registerCommandSchedules(): void
    {
    }

    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    protected function registerConfig(): void
    {
        $configPath = module_path($this->name, config('modules.paths.generator.config.path'));

        if (! is_dir($configPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $config = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $configKey = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $config);
            $segments = explode('.', $this->nameLower.'.'.$configKey);

            $normalized = [];
            foreach ($segments as $segment) {
                if (end($normalized) !== $segment) {
                    $normalized[] = $segment;
                }
            }

            $key = ($config === 'config.php') ? $this->nameLower : implode('.', $normalized);

            $this->publishes([$file->getPathname() => config_path($config)], 'config');
            $this->mergeConfigFromModule($file->getPathname(), $key);
        }
    }

    protected function mergeConfigFromModule(string $path, string $key): void
    {
        $existing = config($key, []);
        $moduleConfig = require $path;

        config([$key => array_replace_recursive($existing, $moduleConfig)]);
    }

    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower.'-module-views']);
        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        Blade::componentNamespace(config('modules.namespace').'\\'.$this->name.'\\View\\Components', $this->nameLower);
    }

    public function provides(): array
    {
        return [];
    }

    private function configureLdapRuntime(): void
    {
        $settings = $this->app->make(SecurityAuthSettingsService::class)->getForView();
        [$host, $portFromHost] = $this->parseLdapHost((string) ($settings['ldap_host'] ?? ''));
        $connectionName = config('ldap.default', 'default');
        $ldapOptions = [];
        $emailAttribute = trim((string) ($settings['ldap_email_attribute'] ?? 'mail')) ?: 'mail';

        if (defined('LDAP_OPT_PROTOCOL_VERSION')) {
            $ldapOptions[LDAP_OPT_PROTOCOL_VERSION] = 3;
        }

        if (defined('LDAP_OPT_REFERRALS')) {
            $ldapOptions[LDAP_OPT_REFERRALS] = 0;
        }

        config([
            "ldap.connections.$connectionName.hosts" => $host !== '' ? [$host] : [env('LDAP_HOST', '127.0.0.1')],
            "ldap.connections.$connectionName.username" => $settings['ldap_anonymous'] ? null : ($settings['ldap_bind_dn'] ?: env('LDAP_USERNAME')),
            "ldap.connections.$connectionName.password" => $settings['ldap_anonymous'] ? null : ($settings['ldap_bind_password'] ?: env('LDAP_PASSWORD')),
            "ldap.connections.$connectionName.port" => $portFromHost ?: (int) ($settings['ldap_port'] ?? env('LDAP_PORT', 389)),
            "ldap.connections.$connectionName.base_dn" => $settings['ldap_base_dn'] ?: env('LDAP_BASE_DN'),
            "ldap.connections.$connectionName.timeout" => (int) env('LDAP_TIMEOUT', 5),
            "ldap.connections.$connectionName.use_ssl" => (bool) ($settings['ldap_use_tls'] ?? env('LDAP_SSL', false)),
            "ldap.connections.$connectionName.use_tls" => (bool) ($settings['ldap_use_starttls'] ?? env('LDAP_TLS', false)),
            "ldap.connections.$connectionName.options" => $ldapOptions,
            'auth.providers.ldap-admin.model' => LdapOpenLdapUser::class,
            'auth.providers.ldap-admin.database.model' => \App\Models\User::class,
            'auth.providers.ldap-admin.database.sync_passwords' => false,
            'auth.providers.ldap-admin.database.sync_attributes' => [
                'name' => 'cn',
                'email' => $emailAttribute,
                'phone' => 'telephonenumber',
            ],
        ]);
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];

        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->nameLower)) {
                $paths[] = $path.'/modules/'.$this->nameLower;
            }
        }

        return $paths;
    }

    /**
     * @return array{0:string,1:int|null}
     */
    protected function parseLdapHost(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return ['', null];
        }

        if (! str_contains($value, ':')) {
            return [$value, null];
        }

        [$host, $port] = array_pad(explode(':', $value, 2), 2, null);

        return [
            trim((string) $host),
            is_numeric($port) ? (int) $port : null,
        ];
    }
}
