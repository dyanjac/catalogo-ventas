<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $this->merge([
            'name' => $this->normalizeText($this->input('name')),
            'sku' => $this->normalizeText($this->input('sku')),
            'slug' => $this->normalizeText($this->input('slug')),
            'description' => $this->normalizeText($this->input('description')),
            'account' => $this->normalizeText($this->input('account')),
            'tax_affectation' => $this->normalizeText($this->input('tax_affectation')),
            'uses_series' => $this->boolean('uses_series'),
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
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:190'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit_measure_id' => ['required', 'integer', 'exists:unit_measures,id'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product?->id)],
            'slug' => ['nullable', 'string', 'max:220', Rule::unique('products', 'slug')->ignore($product?->id)],
            'description' => ['nullable', 'string'],
            'tax_affectation' => ['required', Rule::in(['Gravado', 'Exonerado', 'Inafecto'])],
            'uses_series' => ['nullable', 'boolean'],
            'account' => ['nullable', 'string', 'max:120'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'average_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
