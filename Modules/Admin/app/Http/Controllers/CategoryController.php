<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\OrganizationContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Catalog\Enums\ProductAccountingTreatment;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index');
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'category' => new Category(['accounting_treatment' => ProductAccountingTreatment::Inherit]),
            'accountingTreatments' => ProductAccountingTreatment::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Category::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Categoría creada correctamente.');
    }

    public function edit(Category $category): View
    {
        abort_unless($this->belongsToCurrentOrganization($category), 404);

        return view('admin.categories.edit', [
            'category' => $category,
            'accountingTreatments' => ProductAccountingTreatment::cases(),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        abort_unless($this->belongsToCurrentOrganization($category), 404);

        $category->update($this->validated($request, $category));

        return redirect()->route('admin.categories.index')->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        abort_unless($this->belongsToCurrentOrganization($category), 404);
        abort_if($category->products()->exists(), 422, 'No se puede eliminar una categoría con productos asociados.');

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Categoría eliminada correctamente.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $organizationId = app(OrganizationContextService::class)->currentOrganizationId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('categories', 'name')->where('organization_id', $organizationId)->ignore($category?->id)],
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('categories', 'slug')->where('organization_id', $organizationId)->ignore($category?->id)],
            'description' => ['nullable', 'string'],
            'accounting_treatment' => ['sometimes', Rule::enum(ProductAccountingTreatment::class)],
            'account_revenue' => ['nullable', 'string', 'max:120'],
            'account_receivable' => ['nullable', 'string', 'max:120'],
            'account_inventory' => ['nullable', 'string', 'max:120'],
            'account_cogs' => ['nullable', 'string', 'max:120'],
            'account_tax' => ['nullable', 'string', 'max:120'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        foreach (['account_revenue', 'account_receivable', 'account_inventory', 'account_cogs', 'account_tax'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? trim((string) $data[$field]) : null;
            }
        }

        return $data;
    }

    private function belongsToCurrentOrganization(Category $category): bool
    {
        return (int) $category->organization_id === (int) app(OrganizationContextService::class)->currentOrganizationId();
    }
}
