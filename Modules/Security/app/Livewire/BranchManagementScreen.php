<?php

namespace Modules\Security\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Services\SecurityAuditService;
use Modules\Security\Services\SecurityAuthorizationService;

class BranchManagementScreen extends Component
{
    use WithPagination;

    #[Url(as: 'search', history: true, keep: true)]
    public string $search = '';

    public ?int $selectedBranchId = null;

    public bool $isCreating = false;

    public string $code = '';

    public string $name = '';

    public string $city = '';

    public string $address = '';

    public string $phone = '';

    public bool $is_active = true;

    public bool $is_default = false;

    public ?string $flashMessage = null;

    public string $flashTone = 'success';

    protected ?int $loadedBranchId = null;

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function createBranch(): void
    {
        $this->selectedBranchId = null;
        $this->loadedBranchId = null;
        $this->isCreating = true;
        $this->resetForm();
    }

    public function selectBranch(int $branchId): void
    {
        $branch = SecurityBranch::query()->findOrFail($branchId);

        $this->selectedBranchId = $branch->id;
        $this->code = $branch->code;
        $this->name = $branch->name;
        $this->city = (string) ($branch->city ?? '');
        $this->address = (string) ($branch->address ?? '');
        $this->phone = (string) ($branch->phone ?? '');
        $this->is_active = (bool) $branch->is_active;
        $this->is_default = (bool) $branch->is_default;
        $this->loadedBranchId = $branch->id;
        $this->isCreating = false;
        $this->flashMessage = null;
    }

    public function save(SecurityAuthorizationService $authorization, SecurityAuditService $audit): void
    {
        abort_unless($authorization->hasPermission(auth()->user(), 'security.branches.update'), 403);

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('security_branches', 'code')->ignore($this->selectedBranchId)],
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        if ($validated['is_default']) {
            SecurityBranch::query()->update(['is_default' => false]);
        }

        $branch = SecurityBranch::query()->updateOrCreate(
            ['id' => $this->selectedBranchId],
            [
                'code' => strtoupper(trim($validated['code'])),
                'name' => trim($validated['name']),
                'city' => trim((string) ($validated['city'] ?? '')) ?: null,
                'address' => trim((string) ($validated['address'] ?? '')) ?: null,
                'phone' => trim((string) ($validated['phone'] ?? '')) ?: null,
                'is_active' => (bool) $validated['is_active'],
                'is_default' => (bool) $validated['is_default'],
            ]
        );

        $audit->log(
            eventType: 'authorization',
            eventCode: 'security.branch.saved',
            result: 'success',
            message: 'Se guardo una sucursal de seguridad.',
            actor: auth()->user(),
            target: $branch,
            module: 'security',
            context: [
                'code' => $branch->code,
                'is_default' => $branch->is_default,
            ],
        );

        $this->selectBranch($branch->id);
        $this->flashTone = 'success';
        $this->flashMessage = 'Sucursal guardada correctamente.';
    }

    public function render()
    {
        $branches = SecurityBranch::query()
            ->when(trim($this->search) !== '', function ($query): void {
                $search = trim($this->search);
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(10);

        if (! $this->isCreating && $branches->count() > 0 && ! collect($branches->items())->contains('id', $this->selectedBranchId)) {
            $this->selectBranch((int) $branches->items()[0]->id);
        }

        return view('security::settings.livewire.branch-management-screen', [
            'branches' => $branches,
        ]);
    }

    private function resetForm(): void
    {
        $this->code = '';
        $this->name = '';
        $this->city = '';
        $this->address = '';
        $this->phone = '';
        $this->is_active = true;
        $this->is_default = false;
    }
}
