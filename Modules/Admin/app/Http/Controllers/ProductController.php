<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\UnitMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $query = Product::query()->with(['category', 'unitMeasure', 'mainImage'])->latest('id');

        if ($search = trim((string) request('q'))) {
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($categoryId = request('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($unitMeasureId = request('unit_measure_id')) {
            $query->where('unit_measure_id', $unitMeasureId);
        }

        if (request()->filled('is_active')) {
            $query->where('is_active', (bool) request('is_active'));
        }

        return view('admin.products.index', [
            'products' => $query->paginate(12)->withQueryString(),
            'categories' => Category::orderBy('name')->get(),
            'unitMeasures' => UnitMeasure::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product([
                'tax_affectation' => 'Gravado',
                'requires_accounting_entry' => true,
                'is_active' => true,
            ]),
            'categories' => Category::orderBy('name')->get(),
            'unitMeasures' => UnitMeasure::orderBy('name')->get(),
            'taxAffectations' => $this->taxAffectations(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $this->normalizePayload($request->validated());
        $product = Product::create($data);
        $this->syncProductImage($product, $request->file('image_file'));

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Producto creado correctamente.');
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'unitMeasure', 'images', 'mainImage']);

        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $product->load(['images', 'mainImage']);

        return view('admin.products.edit', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
            'unitMeasures' => UnitMeasure::orderBy('name')->get(),
            'taxAffectations' => $this->taxAffectations(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($this->normalizePayload($request->validated(), $product));
        ProductImage::where('product_id', $product->id)->update(['product_sku' => $product->sku]);
        $this->syncProductImage($product, $request->file('image_file'));

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Producto eliminado correctamente.');
    }

    private function taxAffectations(): array
    {
        return ['Gravado', 'Exonerado', 'Inafecto'];
    }

    private function normalizePayload(array $data, ?Product $product = null): array
    {
        foreach ([
            'purchase_price',
            'sale_price',
            'wholesale_price',
            'average_price',
            'account',
            'account_revenue',
            'account_receivable',
            'account_inventory',
            'account_cogs',
            'account_tax',
            'description',
        ] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $data['stock'] = isset($data['stock']) && $data['stock'] !== '' ? (int) $data['stock'] : 0;
        $data['min_stock'] = isset($data['min_stock']) && $data['min_stock'] !== '' ? (int) $data['min_stock'] : 0;
        $data['uses_series'] = (bool) ($data['uses_series'] ?? false);
        $data['requires_accounting_entry'] = (bool) ($data['requires_accounting_entry'] ?? true);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['tax_affectation'] = $data['tax_affectation'] ?? 'Gravado';

        $data['sku'] = $data['sku'] ?? $this->generateSku();
        $data['slug'] = $this->resolveSlug($data['slug'] ?? null, $data['name'], $product?->id);
        $data['price'] = $data['sale_price'] ?? 0;

        return $data;
    }

    private function resolveSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name);
        $base = $base !== '' ? $base : Str::lower(Str::random(8));
        $candidate = $base;
        $counter = 2;

        while (
            Product::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = "{$base}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function generateSku(): string
    {
        do {
            $sku = 'PRD-' . Str::upper(Str::random(8));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }

    private function syncProductImage(Product $product, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }

        $path = $file->store('products', 'public');

        ProductImage::where('product_id', $product->id)->update(['is_main' => false]);

        ProductImage::create([
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'path' => $path,
            'is_main' => true,
            'sort' => 0,
        ]);

        $product->update(['image' => $path]);
    }
}
