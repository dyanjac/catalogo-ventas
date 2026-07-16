<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Accounting\Http\Controllers\AccountingSettingsController;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Admin\Http\Controllers\CategoryController;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Tests\TestCase;

class AccountingPayloadCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_category_payload_preserves_new_accounting_configuration(): void
    {
        $organization = Organization::query()->where('is_default', true)->firstOrFail();
        $category = Category::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Legacy category',
            'slug' => 'legacy-category',
            'accounting_treatment' => ProductAccountingTreatment::Manual->value,
            'account_revenue' => '701-LEGACY',
        ]);
        $request = Request::create('/admin/categories/'.$category->slug, 'PUT', [
            'name' => 'Legacy category updated',
            'slug' => 'legacy-category-updated',
            'description' => 'Updated by an old client.',
        ]);

        app(CategoryController::class)->update($request, $category);

        $category->refresh();
        $this->assertSame(ProductAccountingTreatment::Manual, $category->accounting_treatment);
        $this->assertSame('701-LEGACY', $category->account_revenue);
    }

    public function test_legacy_accounting_settings_payload_preserves_new_product_defaults(): void
    {
        $organization = Organization::query()->where('is_default', true)->firstOrFail();
        AccountingSetting::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'fiscal_year' => 2026,
                'fiscal_year_start_month' => 1,
                'default_currency' => 'PEN',
                'period_closure_enabled' => false,
                'auto_post_entries' => true,
                'product_accounting_treatment' => ProductAccountingTreatment::Automatic->value,
                'default_account_revenue' => '701-LEGACY',
            ]
        );
        $request = Request::create('/admin/accounting/settings', 'PUT', [
            'fiscal_year' => 2027,
            'fiscal_year_start_month' => 1,
            'default_currency' => 'PEN',
            'period_closure_enabled' => false,
            'auto_post_entries' => true,
        ]);

        app(AccountingSettingsController::class)->update($request);

        $settings = AccountingSetting::query()->where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame(ProductAccountingTreatment::Automatic, $settings->product_accounting_treatment);
        $this->assertSame('701-LEGACY', $settings->default_account_revenue);
        $this->assertSame(2027, (int) $settings->fiscal_year);
    }
}
