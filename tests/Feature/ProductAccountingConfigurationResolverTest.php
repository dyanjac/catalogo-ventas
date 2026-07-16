<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Accounting\Services\ProductAccountingConfigurationResolver;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Tests\TestCase;

class ProductAccountingConfigurationResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_treatment_and_accounts_field_by_field_from_product_category_and_company(): void
    {
        $organization = $this->createOrganization('RESOLVE');
        $category = $this->createCategory($organization, [
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'account_revenue' => '701-CATEGORY',
            'account_tax' => '401-CATEGORY',
        ]);
        AccountingSetting::query()->create([
            'organization_id' => $organization->id,
            'fiscal_year' => 2026,
            'product_accounting_treatment' => ProductAccountingTreatment::Automatic->value,
            'default_account_revenue' => '701-COMPANY',
            'default_account_receivable' => '121-COMPANY',
            'default_account_inventory' => '201-COMPANY',
            'default_account_cogs' => '691-COMPANY',
            'default_account_tax' => '401-COMPANY',
        ]);
        $product = $this->createProduct($organization, $category, [
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'account_receivable' => '122-PRODUCT',
        ]);

        $resolved = app(ProductAccountingConfigurationResolver::class)->resolve($product);

        $this->assertSame(ProductAccountingTreatment::Automatic, $resolved->treatment);
        $this->assertSame('organization', $resolved->treatmentSource);
        $this->assertSame('701-CATEGORY', $resolved->account('revenue'));
        $this->assertSame('category', $resolved->accountSource('revenue'));
        $this->assertSame('122-PRODUCT', $resolved->account('receivable'));
        $this->assertSame('product', $resolved->accountSource('receivable'));
        $this->assertSame('201-COMPANY', $resolved->account('inventory'));
        $this->assertSame('organization', $resolved->accountSource('inventory'));
        $this->assertSame('401-CATEGORY', $resolved->account('tax'));
    }

    public function test_explicit_product_treatment_is_terminal_and_missing_chain_becomes_pending(): void
    {
        $organization = $this->createOrganization('TERMINAL');
        $category = $this->createCategory($organization);
        AccountingSetting::query()->create([
            'organization_id' => $organization->id,
            'fiscal_year' => 2026,
            'product_accounting_treatment' => ProductAccountingTreatment::Automatic->value,
        ]);
        $manualProduct = $this->createProduct($organization, $category, [
            'accounting_treatment' => ProductAccountingTreatment::Manual->value,
        ]);

        $manual = app(ProductAccountingConfigurationResolver::class)->resolve($manualProduct);
        $this->assertSame(ProductAccountingTreatment::Manual, $manual->treatment);
        $this->assertSame('product', $manual->treatmentSource);

        AccountingSetting::query()->where('organization_id', $organization->id)->delete();
        $pendingProduct = $this->createProduct($organization, $category, [
            'sku' => 'PENDING-1',
            'slug' => 'pending-1',
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ]);

        $pending = app(ProductAccountingConfigurationResolver::class)->resolve($pendingProduct);
        $this->assertSame(ProductAccountingTreatment::PendingConfiguration, $pending->treatment);
        $this->assertSame('system', $pending->treatmentSource);
    }

    public function test_it_never_inherits_category_or_company_configuration_from_another_tenant(): void
    {
        $organizationA = $this->createOrganization('TENANT-A');
        $organizationB = $this->createOrganization('TENANT-B');
        $foreignCategory = $this->createCategory($organizationB, [
            'accounting_treatment' => ProductAccountingTreatment::Automatic->value,
            'account_revenue' => 'FOREIGN-701',
        ]);
        AccountingSetting::query()->create([
            'organization_id' => $organizationB->id,
            'fiscal_year' => 2026,
            'product_accounting_treatment' => ProductAccountingTreatment::Automatic->value,
            'default_account_revenue' => 'FOREIGN-COMPANY',
        ]);
        $product = $this->createProduct($organizationA, $foreignCategory, [
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ]);

        $resolved = app(ProductAccountingConfigurationResolver::class)->resolve($product);

        $this->assertSame(ProductAccountingTreatment::PendingConfiguration, $resolved->treatment);
        $this->assertNull($resolved->account('revenue'));
    }

    private function createOrganization(string $code): Organization
    {
        return Organization::query()->create([
            'code' => $code,
            'name' => 'Organization '.$code,
            'slug' => strtolower($code),
            'status' => 'active',
            'environment' => 'demo',
            'is_default' => false,
            'settings_json' => [],
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createCategory(Organization $organization, array $attributes = []): Category
    {
        return Category::query()->create(array_merge([
            'organization_id' => $organization->id,
            'name' => 'Category '.$organization->code.' '.uniqid(),
            'slug' => 'category-'.strtolower($organization->code).'-'.uniqid(),
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function createProduct(Organization $organization, Category $category, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'organization_id' => $organization->id,
            'category_id' => $category->id,
            'name' => 'Product '.$organization->code.' '.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'slug' => 'product-'.strtolower($organization->code).'-'.uniqid(),
            'tax_affectation' => 'Gravado',
            'product_type' => ProductType::PhysicalGood->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
            'price' => 10,
            'stock' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ], $attributes));
    }
}
