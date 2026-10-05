<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Schema;
use Modules\Security\Services\SecurityAuthSettingsService;
use RuntimeException;
use Tests\TestCase;

class SecurityAuthSettingsServiceTest extends TestCase
{
    public function test_view_settings_fall_back_to_configuration_when_database_is_unavailable(): void
    {
        $defaults = config('security.auth');

        Schema::shouldReceive('hasTable')
            ->once()
            ->with('security_auth_settings')
            ->andThrow(new RuntimeException('database unavailable'));

        $this->assertSame(
            $defaults,
            app(SecurityAuthSettingsService::class)->getForView()
        );
    }
}
