<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\UnitMeasure;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true, keep: true)]
    public string $search = '';

    #[Url(as: 'category_id', history: true, keep: true)]
    public string $categoryId = '';

    #[Url(as: 'unit_measure_id', history: true, keep: true)]
    public string $unitMeasureId = '';

    #[Url(as: 'is_active', history: true, keep: true)]
    public string $isActive = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedUnitMeasureId(): void
    {
        $this->resetPage();
    }

    public function updatedIsActive(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryId', 'unitMeasureId', 'isActive']);
        $this->resetPage();
    }

    public function render()
    {
        $products = Product::query()
            ->with(['category', 'unitMeasure', 'mainImage'])
            ->latest('id')
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);

                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($this->categoryId !== '', fn ($query) => $query->where('category_id', $this->categoryId))
            ->when($this->unitMeasureId !== '', fn ($query) => $query->where('unit_measure_id', $this->unitMeasureId))
            ->when($this->isActive !== '', fn ($query) => $query->where('is_active', (bool) $this->isActive))
            ->paginate(12);

        return view('livewire.admin.products-index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(),
            'unitMeasures' => UnitMeasure::query()->orderBy('name')->get(),
        ]);
    }
}
