<?php

namespace Database\Seeders;

use App\Models\CommerceSetting;
use Illuminate\Database\Seeder;

class CommerceSettingSeeder extends Seeder
{
    public function run(): void
    {
        CommerceSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'company_name' => (string) config('commerce.name', 'Name Company'),
                'tax_id' => (string) config('commerce.tax_id', ''),
                'address' => (string) config('commerce.address', ''),
                'phone' => (string) config('commerce.phone', ''),
                'mobile' => (string) config('commerce.mobile', ''),
                'email' => (string) config('commerce.email', ''),
            ]
        );
    }
}
