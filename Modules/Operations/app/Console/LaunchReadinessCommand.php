<?php

declare(strict_types=1);

namespace Modules\Operations\Console;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Modules\Operations\Services\ReadinessService;

final class LaunchReadinessCommand extends Command
{
    protected $signature = 'operations:launch-check
        {--skip-runtime : Valida la configuración sin consultar base de datos ni caché}';

    protected $description = 'Valida la configuración y dependencias necesarias antes de un despliegue productivo';

    public function handle(ReadinessService $readiness): int
    {
        $checks = $this->configurationChecks();

        if (! $this->option('skip-runtime')) {
            foreach ($readiness->inspect()['checks'] as $name => $check) {
                $checks["runtime.{$name}"] = $check;
            }

            $checks['queue.retry_after'] = $this->queueCheck();
        }

        foreach ($checks as $name => $check) {
            $this->line(sprintf('[%s] %s: %s', $check['ok'] ? 'OK' : 'FAIL', $name, $check['detail']));
        }

        return collect($checks)->every(fn (array $check): bool => $check['ok'])
            ? self::SUCCESS
            : self::FAILURE;
    }

    /** @return array<string, array{ok:bool, detail:string}> */
    private function configurationChecks(): array
    {
        $environment = (string) config('app.env');
        $appKey = (string) config('app.key');
        $appKeyIsValid = $this->validAppKey($appKey);
        $appUrl = (string) config('app.url');
        $analyticsEnabled = (bool) config('marketing.analytics.enabled');
        $analyticsDomain = trim((string) config('marketing.analytics.domain'));
        $analyticsScriptUrl = (string) config('marketing.analytics.script_url');
        $analyticsConfigured = ! $analyticsEnabled || (
            $analyticsDomain !== ''
            && filter_var($analyticsScriptUrl, FILTER_VALIDATE_URL) !== false
            && parse_url($analyticsScriptUrl, PHP_URL_SCHEME) === 'https'
        );

        return [
            'app.environment' => [
                'ok' => $environment === 'production',
                'detail' => $environment === 'production' ? 'production' : 'must_be_production',
            ],
            'app.debug' => [
                'ok' => config('app.debug') === false,
                'detail' => config('app.debug') === false ? 'disabled' : 'must_be_disabled',
            ],
            'app.key' => [
                'ok' => $appKeyIsValid,
                'detail' => $appKeyIsValid ? 'configured' : 'missing_or_invalid',
            ],
            'app.url' => [
                'ok' => parse_url($appUrl, PHP_URL_SCHEME) === 'https' && filled(parse_url($appUrl, PHP_URL_HOST)),
                'detail' => parse_url($appUrl, PHP_URL_SCHEME) === 'https' ? 'https' : 'must_use_https',
            ],
            'session.secure_cookie' => [
                'ok' => config('session.secure') === true,
                'detail' => config('session.secure') === true ? 'enabled' : 'must_be_enabled',
            ],
            'marketing.brand' => [
                'ok' => filled(config('marketing.brand_name')),
                'detail' => filled(config('marketing.brand_name')) ? 'configured' : 'missing',
            ],
            'marketing.analytics' => [
                'ok' => $analyticsConfigured,
                'detail' => ! $analyticsEnabled ? 'disabled' : ($analyticsConfigured ? 'configured' : 'invalid_configuration'),
            ],
        ];
    }

    /** @return array{ok:bool, detail:string} */
    private function queueCheck(): array
    {
        $connection = (string) config('queue.default');
        $retryAfter = (int) config("queue.connections.{$connection}.retry_after", 0);
        $required = (int) config('operations.queue.required_retry_after', 960);
        $safe = $connection === 'sync' || $retryAfter >= $required;

        return [
            'ok' => $safe,
            'detail' => $connection === 'sync' ? 'sync' : "{$retryAfter}/required:{$required}",
        ];
    }

    private function validAppKey(string $key): bool
    {
        if ($key === '' || str_contains($key, 'REPLACE_')) {
            return false;
        }

        $decoded = str_starts_with($key, 'base64:')
            ? base64_decode(substr($key, 7), true)
            : $key;

        return is_string($decoded)
            && Encrypter::supported($decoded, (string) config('app.cipher'));
    }
}
