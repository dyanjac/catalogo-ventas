<?php

namespace Modules\Commerce\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Commerce\Entities\CommerceSetting;
use Modules\Commerce\Services\CommerceSettingsService;
use Tests\TestCase;

class CommerceSettingsServiceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_service_uses_config_fallback_without_db_record(): void
    {
        Cache::flush();
        config()->set('commerce.name', 'Modulo Commerce');
        config()->set('commerce.email', 'modulo-commerce@example.test');
        config()->set('commerce.mobile', '+51 911222333');

        $service = app(CommerceSettingsService::class);
        $data = $service->getForView();

        $this->assertSame('Modulo Commerce', $data['name']);
        $this->assertSame('modulo-commerce@example.test', $data['email']);
        $this->assertSame('51911222333', $data['mobile_digits']);
    }

    public function test_module_service_prefers_database_values(): void
    {
        Cache::flush();

        CommerceSetting::query()->create([
            'company_name' => 'Commerce DB',
            'tax_id' => '20123456789',
            'address' => 'Av. Comercio 123',
            'phone' => '+51 01 4445566',
            'mobile' => '+51 987654321',
            'email' => 'db@commerce.test',
        ]);

        $service = app(CommerceSettingsService::class);
        $data = $service->getForView();

        $this->assertSame('Commerce DB', $data['name']);
        $this->assertSame('db@commerce.test', $data['email']);
        $this->assertSame('51987654321', $data['mobile_digits']);
        $this->assertSame('51014445566', $data['phone_digits']);
    }
}
