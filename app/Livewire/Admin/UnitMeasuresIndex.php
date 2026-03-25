<?php

namespace App\Livewire\Admin;

use App\Models\UnitMeasure;
use Livewire\Component;
use Livewire\WithPagination;

class UnitMeasuresIndex extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.admin.unit-measures-index', [
            'unitMeasures' => UnitMeasure::query()->forCurrentOrganization()
                ->withCount('products')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }
}
