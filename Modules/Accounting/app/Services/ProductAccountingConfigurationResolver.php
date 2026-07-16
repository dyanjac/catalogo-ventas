<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Modules\Accounting\Data\ResolvedProductAccountingConfiguration;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Enums\ProductAccountingTreatment;

final class ProductAccountingConfigurationResolver
{
    /** @var array<string, string> */
    private const PRODUCT_ACCOUNT_FIELDS = [
        'revenue' => 'account_revenue',
        'receivable' => 'account_receivable',
        'inventory' => 'account_inventory',
        'cogs' => 'account_cogs',
        'tax' => 'account_tax',
    ];

    /** @var array<string, string> */
    private const COMPANY_ACCOUNT_FIELDS = [
        'revenue' => 'default_account_revenue',
        'receivable' => 'default_account_receivable',
        'inventory' => 'default_account_inventory',
        'cogs' => 'default_account_cogs',
        'tax' => 'default_account_tax',
    ];

    public function resolve(Product $product): ResolvedProductAccountingConfiguration
    {
        $organizationId = (int) $product->organization_id;
        $category = $this->resolveCategory($product, $organizationId);
        $settings = AccountingSetting::query()
            ->where('organization_id', $organizationId)
            ->first();

        [$treatment, $treatmentSource] = $this->resolveTreatment($product, $category, $settings);
        [$accounts, $accountSources] = $this->resolveAccounts($product, $category, $settings);

        return new ResolvedProductAccountingConfiguration(
            treatment: $treatment,
            treatmentSource: $treatmentSource,
            accounts: $accounts,
            accountSources: $accountSources,
        );
    }

    private function resolveCategory(Product $product, int $organizationId): ?Category
    {
        $category = $product->relationLoaded('category')
            ? $product->category
            : $product->category()->first();

        if (! $category || (int) $category->organization_id !== $organizationId) {
            return null;
        }

        return $category;
    }

    /**
     * @return array{ProductAccountingTreatment, string}
     */
    private function resolveTreatment(Product $product, ?Category $category, ?AccountingSetting $settings): array
    {
        $candidates = [
            'product' => $product->accounting_treatment,
            'category' => $category?->accounting_treatment,
            'organization' => $settings?->product_accounting_treatment,
        ];

        foreach ($candidates as $source => $candidate) {
            $treatment = $this->normalizeTreatment($candidate);

            if ($treatment && $treatment !== ProductAccountingTreatment::Inherit) {
                return [$treatment, $source];
            }
        }

        return [ProductAccountingTreatment::PendingConfiguration, 'system'];
    }

    /**
     * @return array{array<string, string|null>, array<string, string|null>}
     */
    private function resolveAccounts(Product $product, ?Category $category, ?AccountingSetting $settings): array
    {
        $accounts = [];
        $sources = [];

        foreach (self::PRODUCT_ACCOUNT_FIELDS as $name => $field) {
            $companyField = self::COMPANY_ACCOUNT_FIELDS[$name];
            $candidates = [
                'product' => $product->{$field},
                'category' => $category?->{$field},
                'organization' => $settings?->{$companyField},
            ];

            $accounts[$name] = null;
            $sources[$name] = null;

            foreach ($candidates as $source => $candidate) {
                $value = trim((string) $candidate);

                if ($value !== '') {
                    $accounts[$name] = $value;
                    $sources[$name] = $source;
                    break;
                }
            }
        }

        return [$accounts, $sources];
    }

    private function normalizeTreatment(mixed $value): ?ProductAccountingTreatment
    {
        if ($value instanceof ProductAccountingTreatment) {
            return $value;
        }

        return is_string($value) ? ProductAccountingTreatment::tryFrom($value) : null;
    }
}
