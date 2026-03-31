<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Billing\Models\BillingSetting;
use Modules\Commerce\Entities\CommerceSetting;
use Modules\Security\Models\SecurityBranch;

class DefaultOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->updateOrCreate(
            ['code' => 'DEFAULT'],
            [
                'name' => (string) config('commerce.name', 'Organizacion Base'),
                'slug' => 'default',
                'tax_id' => (string) config('commerce.tax_id', ''),
                'status' => 'active',
                'environment' => 'production',
                'is_default' => true,
                'settings_json' => [
                    'seeded_via' => 'default_installation',
                    'seeded_at' => now()->toDateTimeString(),
                ],
            ]
        );

        Organization::query()
            ->where('id', '!=', $organization->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $branch = SecurityBranch::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'code' => 'MAIN',
            ],
            [
                'name' => 'Sucursal principal',
                'city' => null,
                'address' => null,
                'phone' => null,
                'is_active' => true,
                'is_default' => true,
            ]
        );

        SecurityBranch::query()
            ->where('organization_id', $organization->id)
            ->where('id', '!=', $branch->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        CommerceSetting::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'company_name' => $organization->name,
                'tax_id' => $organization->tax_id,
                'address' => (string) config('commerce.address', ''),
                'phone' => (string) config('commerce.phone', ''),
                'mobile' => (string) config('commerce.mobile', ''),
                'email' => (string) config('commerce.email', ''),
            ]
        );

        BillingSetting::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'enabled' => false,
                'country' => 'PE',
                'provider' => 'sunat_greenter',
                'environment' => 'beta',
                'dispatch_mode' => 'sync',
                'queue_connection' => null,
                'queue_name' => null,
                'provider_credentials' => [],
                'invoice_series' => 'F001',
                'receipt_series' => 'B001',
                'credit_note_series' => 'FC01',
                'debit_note_series' => 'FD01',
                'default_invoice_operation_code' => '01',
                'default_receipt_operation_code' => '01',
            ]
        );

        AccountingSetting::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'fiscal_year' => (int) now()->format('Y'),
                'fiscal_year_start_month' => 1,
                'default_currency' => 'PEN',
                'period_closure_enabled' => false,
                'auto_post_entries' => false,
            ]
        );
    }
}
