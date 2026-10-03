<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_launch_check_accepts_safe_production_configuration(): void
    {
        config()->set([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://erp.example.test',
            'session.secure' => true,
            'marketing.brand_name' => 'MetisHub ERP',
            'marketing.analytics.enabled' => false,
        ]);

        $this->artisan('operations:launch-check', ['--skip-runtime' => true])
            ->expectsOutputToContain('[OK] app.environment: production')
            ->expectsOutputToContain('[OK] session.secure_cookie: enabled')
            ->expectsOutputToContain('[OK] marketing.analytics: disabled')
            ->assertSuccessful();
    }

    public function test_launch_check_rejects_unsafe_configuration_without_printing_secrets(): void
    {
        $secret = 'should-never-be-printed';
        config()->set([
            'app.env' => 'local',
            'app.debug' => true,
            'app.key' => $secret,
            'app.url' => 'http://erp.example.test',
            'session.secure' => false,
            'marketing.brand_name' => '',
            'marketing.analytics.enabled' => true,
            'marketing.analytics.domain' => '',
            'marketing.analytics.script_url' => 'http://analytics.example.test/script.js',
        ]);

        $this->artisan('operations:launch-check', ['--skip-runtime' => true])
            ->expectsOutputToContain('[FAIL] app.environment: must_be_production')
            ->expectsOutputToContain('[FAIL] app.debug: must_be_disabled')
            ->expectsOutputToContain('[FAIL] app.url: must_use_https')
            ->expectsOutputToContain('[FAIL] marketing.analytics: invalid_configuration')
            ->doesntExpectOutputToContain($secret)
            ->assertFailed();
    }

    public function test_launch_check_includes_runtime_and_queue_readiness(): void
    {
        config()->set([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://erp.example.test',
            'session.secure' => true,
            'marketing.brand_name' => 'MetisHub ERP',
            'marketing.analytics.enabled' => false,
            'queue.default' => 'sync',
        ]);

        $this->artisan('operations:launch-check')
            ->expectsOutputToContain('[OK] runtime.database: available')
            ->expectsOutputToContain('[OK] runtime.cache: read_write')
            ->expectsOutputToContain('[OK] runtime.schema: current')
            ->expectsOutputToContain('[OK] queue.retry_after: sync')
            ->assertSuccessful();
    }
}
