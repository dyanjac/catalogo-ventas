<?php

namespace Modules\Security\Livewire;

use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Models\SecurityRole;
use Modules\Security\Services\SecurityAuditService;
use Modules\Security\Services\SecurityAuthorizationService;

class UserRoleAssignmentsScreen extends Component
{
    use WithPagination;

    #[Url(as: 'search', history: true, keep: true)]
    public string $search = '';

    public ?int $selectedUserId = null;

    public ?int $selectedBranchId = null;

    public array $selectedRoleIds = [];

    public array $roleScopes = [];

    public ?string $flashMessage = null;

    public string $flashTone = 'success';

    protected ?int $loadedUserId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function selectUser(int $userId): void
    {
        $user = User::query()->with(['roles' => fn ($query) => $query->orderBy('name'), 'branch'])->findOrFail($userId);

        $this->selectedUserId = $user->id;
        $this->selectedBranchId = $user->branch_id ? (int) $user->branch_id : null;
        $this->selectedRoleIds = $user->roles
            ->filter(fn ($role) => (bool) data_get($role, 'pivot.is_active', false))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $this->roleScopes = $user->roles
            ->mapWithKeys(fn ($role) => [$role->id => $role->pivot->scope ?: 'all'])
            ->all();
        $this->loadedUserId = $user->id;
        $this->flashMessage = null;
    }

    public function save(SecurityAuthorizationService $authorization, SecurityAuditService $audit): void
    {
        abort_unless($authorization->hasPermission(auth()->user(), 'security.users.assign'), 403);

        $validated = $this->validate([
            'selectedUserId' => ['required', 'integer', 'exists:users,id'],
            'selectedBranchId' => ['nullable', 'integer', 'exists:security_branches,id'],
            'selectedRoleIds' => ['array'],
            'selectedRoleIds.*' => ['integer', 'exists:security_roles,id'],
            'roleScopes' => ['array'],
        ]);

        $user = User::query()->findOrFail($validated['selectedUserId']);
        $roles = SecurityRole::query()->whereIn('id', $validated['selectedRoleIds'])->get();

        $payload = $roles->mapWithKeys(function (SecurityRole $role) use ($validated): array {
            return [
                $role->id => [
                    'scope' => $validated['roleScopes'][$role->id] ?? 'all',
                    'is_active' => true,
                    'context' => null,
                ],
            ];
        })->all();

        $user->roles()->sync($payload);

        $legacyRole = $user->role;

        if ($roles->contains('code', 'super_admin')) {
            $legacyRole = 'super_admin';
        } elseif ($roles->contains('code', 'customer')) {
            $legacyRole = 'customer';
        }

        $user->forceFill([
            'role' => $legacyRole,
            'branch_id' => $validated['selectedBranchId'] ?? null,
        ])->save();

        $audit->log(
            eventType: 'authorization',
            eventCode: 'security.user.roles.updated',
            result: 'success',
            message: 'Se actualizaron los roles y la sucursal de un usuario.',
            actor: auth()->user(),
            target: $user,
            module: 'security',
            context: [
                'assigned_roles' => $roles->pluck('code')->values()->all(),
                'branch_id' => $user->branch_id,
            ],
        );

        $this->selectUser($user->id);
        $this->flashTone = 'success';
        $this->flashMessage = 'Accesos y sucursal del usuario actualizados correctamente.';
    }

    public function render(SecurityAuthorizationService $authorization)
    {
        $users = User::query()
            ->with(['roles' => fn ($query) => $query->orderBy('name'), 'branch'])
            ->when(trim($this->search) !== '', function ($query): void {
                $search = trim($this->search);
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10);

        if ($users->count() > 0 && ! collect($users->items())->contains('id', $this->selectedUserId)) {
            $this->selectUser((int) $users->items()[0]->id);
        }

        $selectedUser = $this->selectedUserId
            ? User::query()->with(['roles' => fn ($query) => $query->orderBy('name'), 'branch'])->find($this->selectedUserId)
            : null;

        if ($selectedUser && $this->loadedUserId !== $selectedUser->id) {
            $this->selectUser($selectedUser->id);
        }

        return view('security::settings.livewire.user-role-assignments-screen', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'roles' => SecurityRole::query()->where('is_active', true)->orderBy('name')->get(),
            'branches' => SecurityBranch::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'authorization' => $authorization,
        ]);
    }
}
