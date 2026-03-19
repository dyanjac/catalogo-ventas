<?php

namespace Modules\Security\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Security\Models\SecurityModule;

class SecurityAuthorizationService
{
    protected array $roleCodesCache = [];

    protected array $moduleAccessCache = [];

    protected array $permissionCache = [];

    protected array $navigationModulesCache = [];

    public function hasRole(?User $user, string $roleCode): bool
    {
        if (! $user) {
            return false;
        }

        return $this->resolveRoleCodes($user)->contains($roleCode);
    }

    public function roleCodes(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        return $this->resolveRoleCodes($user);
    }

    public function canAccessAdminPanel(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->hasRole($user, 'super_admin')) {
            return true;
        }

        return $this->resolveRoleCodes($user)
            ->reject(fn (string $code) => $code === 'customer')
            ->isNotEmpty();
    }

    public function canAccessModule(?User $user, string $moduleCode): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->hasRole($user, 'super_admin')) {
            return true;
        }

        if (isset($this->moduleAccessCache[$user->id][$moduleCode])) {
            return $this->moduleAccessCache[$user->id][$moduleCode];
        }

        $allowed = DB::table('security_role_module_access as access')
            ->join('security_roles as roles', 'roles.id', '=', 'access.role_id')
            ->join('security_modules as modules', 'modules.id', '=', 'access.module_id')
            ->join('security_user_roles as user_roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->where('user_roles.is_active', true)
            ->where('roles.is_active', true)
            ->where('modules.code', $moduleCode)
            ->whereIn('access.access_level', ['readonly', 'limited', 'full', 'placeholder'])
            ->exists();

        return $this->moduleAccessCache[$user->id][$moduleCode] = $allowed;
    }

    public function hasPermission(?User $user, string $permissionCode): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->hasRole($user, 'super_admin')) {
            return true;
        }

        return $this->resolvePermissionMap($user)->get($permissionCode, false);
    }

    public function modulesForNavigation(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        if (isset($this->navigationModulesCache[$user->id])) {
            return $this->navigationModulesCache[$user->id];
        }

        if ($this->hasRole($user, 'super_admin')) {
            return $this->navigationModulesCache[$user->id] = SecurityModule::query()
                ->where('navigation_visible', true)
                ->orderBy('sort_order')
                ->get();
        }

        return $this->navigationModulesCache[$user->id] = SecurityModule::query()
            ->select('security_modules.*')
            ->join('security_role_module_access as access', 'access.module_id', '=', 'security_modules.id')
            ->join('security_roles as roles', 'roles.id', '=', 'access.role_id')
            ->join('security_user_roles as user_roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->where('user_roles.is_active', true)
            ->where('roles.is_active', true)
            ->where('security_modules.navigation_visible', true)
            ->where('access.navigation_visible', true)
            ->whereIn('access.access_level', ['readonly', 'limited', 'full', 'placeholder'])
            ->orderBy('security_modules.sort_order')
            ->distinct()
            ->get();
    }

    protected function resolvePermissionMap(User $user): Collection
    {
        if (isset($this->permissionCache[$user->id])) {
            return collect($this->permissionCache[$user->id]);
        }

        $permissions = DB::table('security_role_permissions as role_permissions')
            ->join('security_roles as roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('security_permissions as permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('security_user_roles as user_roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->where('user_roles.is_active', true)
            ->where('roles.is_active', true)
            ->pluck('permissions.code')
            ->map(fn (string $code) => trim($code))
            ->filter()
            ->unique()
            ->values()
            ->mapWithKeys(fn (string $code) => [$code => true]);

        return collect($this->permissionCache[$user->id] = $permissions->all());
    }

    protected function resolveRoleCodes(User $user): Collection
    {
        if (isset($this->roleCodesCache[$user->id])) {
            return collect($this->roleCodesCache[$user->id]);
        }

        $codes = DB::table('security_user_roles as user_roles')
            ->join('security_roles as roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $user->id)
            ->where('user_roles.is_active', true)
            ->where('roles.is_active', true)
            ->pluck('roles.code')
            ->map(fn (string $code) => trim($code))
            ->filter()
            ->values()
            ->all();

        return collect($this->roleCodesCache[$user->id] = array_values(array_unique($codes)));
    }
}
