<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use Livewire\Component;
use Livewire\WithPagination;

class CategoriesIndex extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.admin.categories-index', [
            'categories' => Category::query()->forCurrentOrganization()
                ->withCount('products')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }
}
