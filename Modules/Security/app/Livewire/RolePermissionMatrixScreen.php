<?php

namespace Modules\Security\Livewire;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Modules\Security\Models\SecurityModule;
use Modules\Security\Models\SecurityRole;
use Modules\Security\Services\SecurityAuditService;
use Modules\Security\Services\SecurityAuthorizationService;

class RolePermissionMatrixScreen extends Component
{
    public string $roleSearch = '';

    public string $permissionSearch = '';

    public string $moduleFilter = 'all';

    public ?int $selectedRoleId = null;

    public array $selectedPermissionIds = [];

    public array $moduleAccessLevels = [];

    public array $moduleNavigationVisibility = [];

    public ?string $flashMessage = null;

    public string $flashTone = 'success';

    public array $initialPermissionIds = [];

    public array $initialModuleAccessLevels = [];

    public array $initialModuleNavigationVisibility = [];

    protected ?int $loadedRoleId = null;

    public function mount(): void
    {
        $role = SecurityRole::query()->where('is_active', true)->orderBy('name')->first();

        if ($role) {
            $this->selectRole($role->id);
        }
    }

    public function updatedSelectedRoleId($value): void
    {
        if ($value) {
            $this->selectRole((int) $value);
        }
    }

    public function clearNotice(): void
    {
        $this->flashMessage = null;
    }

    public function selectRole(int $roleId): void
    {
        $role = SecurityRole::query()->with(['permissions:id', 'modules:id'])->findOrFail($roleId);

        $this->selectedRoleId = $role->id;
        $this->selectedPermissionIds = $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->moduleAccessLevels = $role->modules
            ->mapWithKeys(fn (SecurityModule $module) => [$module->id => $module->pivot->access_level])
            ->all();
        $this->moduleNavigationVisibility = $role->modules
            ->mapWithKeys(fn (SecurityModule $module) => [$module->id => (bool) $module->pivot->navigation_visible])
            ->all();
        $this->loadedRoleId = $role->id;
        $this->syncSnapshot();
        $this->flashMessage = null;
    }

    public function selectModulePermissions(int $moduleId): void
    {
        $permissionIds = SecurityModule::query()
            ->with('permissions:id,module_id')
            ->findOrFail($moduleId)
            ->permissions
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->selectedPermissionIds = collect($this->selectedPermissionIds)
            ->merge($permissionIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function clearModulePermissions(int $moduleId): void
    {
        $permissionIds = SecurityModule::query()
            ->with('permissions:id,module_id')
            ->findOrFail($moduleId)
            ->permissions
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->selectedPermissionIds = collect($this->selectedPermissionIds)
            ->reject(fn ($id) => in_array((int) $id, $permissionIds, true))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function applyAccessPreset(string $level): void
    {
        abort_unless(in_array($level, ['none', 'readonly', 'limited', 'full', 'placeholder'], true), 404);

        $moduleIds = SecurityModule::query()->pluck('id')->map(fn ($id) => (int) $id);

        foreach ($moduleIds as $moduleId) {
            $this->moduleAccessLevels[$moduleId] = $level;
            $this->moduleNavigationVisibility[$moduleId] = $level !== 'none';
        }
    }

    public function save(SecurityAuthorizationService $authorization, SecurityAuditService $audit): void
    {
        abort_unless($authorization->hasPermission(auth()->user(), 'security.permissions.assign'), 403);

        $validated = $this->validate([
            'selectedRoleId' => ['required', 'integer', 'exists:security_roles,id'],
            'selectedPermissionIds' => ['array'],
            'selectedPermissionIds.*' => ['integer', 'exists:security_permissions,id'],
            'moduleAccessLevels' => ['array'],
            'moduleAccessLevels.*' => [Rule::in(['none', 'readonly', 'limited', 'full', 'placeholder'])],
            'moduleNavigationVisibility' => ['array'],
        ]);

        $role = SecurityRole::query()->findOrFail($validated['selectedRoleId']);
        $modules = SecurityModule::query()->orderBy('sort_order')->get();

        $moduleSync = $modules->mapWithKeys(function (SecurityModule $module) use ($validated): array {
            $level = $validated['moduleAccessLevels'][$module->id] ?? 'none';

            return [
                $module->id => [
                    'access_level' => $level,
                    'navigation_visible' => $level !== 'none' && (bool) ($validated['moduleNavigationVisibility'][$module->id] ?? false),
                ],
            ];
        })->all();

        $role->permissions()->sync(collect($validated['selectedPermissionIds'] ?? [])->map(fn ($id) => (int) $id)->all());
        $role->modules()->sync($moduleSync);

        $audit->log(
            eventType: 'authorization',
            eventCode: 'security.role.permissions.updated',
            result: 'success',
            message: 'Se actualizaron permisos y modulos de un rol.',
            actor: auth()->user(),
            module: 'security',
            context: [
                'role_id' => $role->id,
                'role_code' => $role->code,
                'permission_count' => count($validated['selectedPermissionIds'] ?? []),
            ],
        );

        $this->selectRole($role->id);
        $this->flashTone = 'success';
        $this->flashMessage = 'Permisos y modulos del rol actualizados correctamente.';
    }

    public function hasUnsavedChanges(): bool
    {
        return $this->normalizePermissionIds($this->selectedPermissionIds) !== $this->normalizePermissionIds($this->initialPermissionIds)
            || $this->normalizeAssocArray($this->moduleAccessLevels) !== $this->normalizeAssocArray($this->initialModuleAccessLevels)
            || $this->normalizeAssocArray($this->moduleNavigationVisibility) !== $this->normalizeAssocArray($this->initialModuleNavigationVisibility);
    }

    public function render()
    {
        $roles = SecurityRole::query()
            ->withCount(['permissions', 'users'])
            ->when(trim($this->roleSearch) !== '', function ($query): void {
                $search = trim($this->roleSearch);
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        if ($roles->isNotEmpty() && ! $roles->contains('id', $this->selectedRoleId)) {
            $this->selectRole((int) $roles->first()->id);
        }

        $selectedRole = $this->selectedRoleId
            ? SecurityRole::query()->withCount('users')->with(['permissions:id', 'modules:id'])->find($this->selectedRoleId)
            : null;

        if ($selectedRole && $this->loadedRoleId !== $selectedRole->id) {
            $this->selectRole($selectedRole->id);
        }

        $modules = SecurityModule::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('code')])
            ->orderBy('sort_order')
            ->get();

        $permissionSearch = mb_strtolower(trim($this->permissionSearch));

        $filteredModules = $modules
            ->when($this->moduleFilter !== 'all', fn ($collection) => $collection->where('code', $this->moduleFilter))
            ->map(function (SecurityModule $module) use ($permissionSearch) {
                if ($permissionSearch === '') {
                    return $module;
                }

                $module->setRelation(
                    'permissions',
                    $module->permissions->filter(function ($permission) use ($permissionSearch): bool {
                        return str_contains(mb_strtolower($permission->code), $permissionSearch)
                            || str_contains(mb_strtolower($permission->resource), $permissionSearch)
                            || str_contains(mb_strtolower($permission->action), $permissionSearch);
                    })->values()
                );

                return $module;
            })
            ->filter(function (SecurityModule $module) use ($permissionSearch): bool {
                if ($permissionSearch === '') {
                    return true;
                }

                return $module->permissions->isNotEmpty();
            })
            ->values();

        return view('security::settings.livewire.role-permission-matrix-screen', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'modules' => $modules,
            'filteredModules' => $filteredModules,
            'accessLevelOptions' => [
                'none' => 'Sin acceso',
                'readonly' => 'Solo lectura',
                'limited' => 'Operativo limitado',
                'full' => 'Control total',
                'placeholder' => 'En construccion',
            ],
        ]);
    }

    private function syncSnapshot(): void
    {
        $this->initialPermissionIds = $this->normalizePermissionIds($this->selectedPermissionIds);
        $this->initialModuleAccessLevels = $this->normalizeAssocArray($this->moduleAccessLevels);
        $this->initialModuleNavigationVisibility = $this->normalizeAssocArray($this->moduleNavigationVisibility);
    }

    private function normalizePermissionIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalizeAssocArray(array $values): array
    {
        ksort($values);

        return collect($values)
            ->mapWithKeys(fn ($value, $key) => [(int) $key => is_bool($value) ? $value : (string) $value])
            ->all();
    }
}
