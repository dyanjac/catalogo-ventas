<?php

namespace App\Http\Requests;

use App\Services\OrganizationContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $accountingTreatment = $this->normalizeText($this->input('accounting_treatment'));
        if ($accountingTreatment === null && $this->has('requires_accounting_entry')) {
            $accountingTreatment = ProductAccountingTreatment::fromLegacyFlag(
                $this->boolean('requires_accounting_entry')
            )->value;
        }
        $accountingTreatment ??= ProductAccountingTreatment::Inherit->value;

        $this->merge([
            'name' => $this->normalizeText($this->input('name')),
            'sku' => $this->normalizeText($this->input('sku')),
            'slug' => $this->normalizeText($this->input('slug')),
            'description' => $this->normalizeText($this->input('description')),
            'account' => $this->normalizeText($this->input('account')),
            'account_revenue' => $this->normalizeText($this->input('account_revenue')),
            'account_deferred_revenue' => $this->normalizeText($this->input('account_deferred_revenue')),
            'account_receivable' => $this->normalizeText($this->input('account_receivable')),
            'account_inventory' => $this->normalizeText($this->input('account_inventory')),
            'account_cogs' => $this->normalizeText($this->input('account_cogs')),
            'account_tax' => $this->normalizeText($this->input('account_tax')),
            'tax_affectation' => $this->normalizeText($this->input('tax_affectation')),
            'product_type' => $this->normalizeText($this->input('product_type')) ?? ProductType::PhysicalGood->value,
            'accounting_treatment' => $accountingTreatment,
            'uses_series' => $this->boolean('uses_series'),
            'requires_accounting_entry' => ProductAccountingTreatment::tryFrom($accountingTreatment)?->requiresLegacyAccountingEntry() ?? false,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    private function normalizeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = app(OrganizationContextService::class)->currentOrganizationId();

        return [
            'name' => ['required', 'string', 'max:190'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('organization_id', $organizationId)],
            'unit_measure_id' => ['required', 'integer', Rule::exists('unit_measures', 'id')->where('organization_id', $organizationId)],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->where('organization_id', $organizationId)],
            'slug' => ['nullable', 'string', 'max:220', Rule::unique('products', 'slug')->where('organization_id', $organizationId)],
            'description' => ['nullable', 'string'],
            'tax_affectation' => ['required', Rule::in(['Gravado', 'Exonerado', 'Inafecto'])],
            'product_type' => ['required', Rule::enum(ProductType::class)],
            'accounting_treatment' => ['required', Rule::enum(ProductAccountingTreatment::class)],
            'uses_series' => ['nullable', 'boolean'],
            'account' => ['nullable', 'string', 'max:120'],
            'requires_accounting_entry' => ['nullable', 'boolean'],
            'account_revenue' => ['nullable', 'string', 'max:120'],
            'account_deferred_revenue' => ['nullable', 'string', 'max:120'],
            'account_receivable' => ['nullable', 'string', 'max:120'],
            'account_inventory' => ['nullable', 'string', 'max:120'],
            'account_cogs' => ['nullable', 'string', 'max:120'],
            'account_tax' => ['nullable', 'string', 'max:120'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'image_file' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
