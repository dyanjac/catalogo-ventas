<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Security\Models\SecurityRole;
use Modules\Security\Services\SecurityScopeService;

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

    public function render(SecurityScopeService $scopeService)
    {
        $query = User::query()
            ->with(['roles' => fn ($query) => $query->orderBy('name')])
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);

                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                });
            })
            ->when($this->role !== '', function ($query) {
                $role = $this->role;

                $query->whereExists(function ($roleQuery) use ($role) {
                    $roleQuery->selectRaw('1')
                        ->from('security_user_roles as user_roles')
                        ->join('security_roles as roles', 'roles.id', '=', 'user_roles.role_id')
                        ->whereColumn('user_roles.user_id', 'users.id')
                        ->where('user_roles.is_active', true)
                        ->where('roles.is_active', true)
                        ->where('roles.code', $role);
                });
            });

        $customers = $scopeService
            ->scopeUsers($query, auth()->user(), 'customers')
            ->latest('id')
            ->paginate(12);

        return view('livewire.admin.customers-index', [
            'customers' => $customers,
            'roleOptions' => SecurityRole::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }
}
