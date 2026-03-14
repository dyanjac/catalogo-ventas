<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CustomersIndex extends Component
{
    use WithPagination;

    #[Url(as: 'search', history: true, keep: true)]
    public string $search = '';

    #[Url(as: 'role', history: true, keep: true)]
    public string $role = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'role']);
        $this->resetPage();
    }

    public function render()
    {
        $customers = User::query()
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);

                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                });
            })
            ->when($this->role !== '', fn ($query) => $query->where('role', $this->role))
            ->latest('id')
            ->paginate(12);

        return view('livewire.admin.customers-index', [
            'customers' => $customers,
        ]);
    }
}
