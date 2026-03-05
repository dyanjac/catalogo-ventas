<?php

namespace Tests\Feature;

use App\Models\CommerceSetting;
use App\Services\CommerceSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CommerceSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_uses_config_fallback_when_no_database_setting_exists(): void
    {
        Cache::flush();
        config()->set('commerce.name', 'Fallback Company');
        config()->set('commerce.email', 'fallback@example.test');
        config()->set('commerce.mobile', '+51 900000000');
        config()->set('commerce.logo', 'img/logo-fallback.png');

        $service = app(CommerceSettingsService::class);
        $data = $service->getForView();

        $this->assertSame('Fallback Company', $data['name']);
        $this->assertSame('fallback@example.test', $data['email']);
        $this->assertSame('+51 900000000', $data['mobile']);
        $this->assertSame('51900000000', $data['mobile_digits']);
    }

    public function test_service_prefers_database_setting_over_config_values(): void
    {
        Cache::flush();
        config()->set('commerce.name', 'Fallback Company');
        config()->set('commerce.email', 'fallback@example.test');

        CommerceSetting::query()->create([
            'company_name' => 'DB Company',
            'tax_id' => '20123456789',
            'address' => 'Av. Central 123',
            'phone' => '+51 01 1234567',
            'mobile' => '+51 999888777',
            'email' => 'ventas@db-company.test',
        ]);

        $service = app(CommerceSettingsService::class);
        $data = $service->getForView();

        $this->assertSame('DB Company', $data['name']);
        $this->assertSame('ventas@db-company.test', $data['email']);
        $this->assertSame('51999888777', $data['mobile_digits']);
        $this->assertSame('51011234567', $data['phone_digits']);
    }
}
