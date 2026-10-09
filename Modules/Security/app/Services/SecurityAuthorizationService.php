<?php

namespace Modules\Security\Services;

use App\Models\User;
use App\Services\SharedNavigationCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Security\Models\SecurityModule;

class SecurityAuthorizationService
{
    protected array $roleCodesCache = [];

    protected array $moduleAccessCache = [];

    protected array $permissionCache = [];

    protected array $navigationCache = [];

    public function __construct(
        private readonly OrganizationEntitlementService $entitlements,
        private readonly SharedNavigationCache $sharedCache
    ) {}

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

        $hasAccess = $this->hasRole($user, 'super_admin')
            || $this->resolveModuleAccessMap($user)->get($moduleCode, false);

        return $hasAccess
            && $this->entitlements->hasModuleCapability($moduleCode, $user->organization);
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

        $organizationKey = $user->organization_id ?: 'default';
        $entitlementsRevision = $user->organization_id
            ? $this->entitlements->revision((int) $user->organization_id)
            : 0;

        if (isset($this->navigationCache[$user->id][$organizationKey])
            && $this->navigationCache[$user->id][$organizationKey]['entitlements_revision'] === $entitlementsRevision) {
            return collect($this->navigationCache[$user->id][$organizationKey]['modules']);
        }

        $cached = $this->sharedCache->get($this->cacheTags($user), 'navigation');

        if (is_array($cached)) {
            $modules = SecurityModule::query()->hydrate($cached);
            $this->navigationCache[$user->id][$organizationKey] = [
                'entitlements_revision' => $entitlementsRevision,
                'modules' => $modules->all(),
            ];

            return $modules;
        }

        $modules = $this->hasRole($user, 'super_admin')
            ? SecurityModule::query()->where('navigation_visible', true)->orderBy('sort_order')->get()
            : SecurityModule::query()
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

        $visibleModules = $modules
            ->filter(fn (SecurityModule $module): bool => $this->entitlements->hasModuleCapability($module->code, $user->organization))
            ->values();

        $this->sharedCache->put(
            $this->cacheTags($user),
            'navigation',
            $visibleModules->map(fn (SecurityModule $module): array => $module->getAttributes())->all()
        );

        $this->navigationCache[$user->id][$organizationKey] = [
            'entitlements_revision' => $entitlementsRevision,
            'modules' => $visibleModules->all(),
        ];

        return $visibleModules;
    }

    public function forgetUser(User|int $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        unset(
            $this->roleCodesCache[$userId],
            $this->moduleAccessCache[$userId],
            $this->permissionCache[$userId],
            $this->navigationCache[$userId]
        );

        $this->sharedCache->flushTag($this->userCacheTag($userId));
    }

    public function forgetAll(): void
    {
        $this->roleCodesCache = [];
        $this->moduleAccessCache = [];
        $this->permissionCache = [];
        $this->navigationCache = [];
        $this->sharedCache->flushTag('navigation-response:authorization');
    }

    protected function resolvePermissionMap(User $user): Collection
    {
        if (isset($this->permissionCache[$user->id])) {
            return collect($this->permissionCache[$user->id]);
        }

        $cached = $this->sharedCache->get($this->cacheTags($user), 'permissions');

        if (is_array($cached)) {
            return collect($this->permissionCache[$user->id] = $cached);
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

        $resolved = $permissions->all();
        $this->sharedCache->put($this->cacheTags($user), 'permissions', $resolved);

        return collect($this->permissionCache[$user->id] = $resolved);
    }

    protected function resolveRoleCodes(User $user): Collection
    {
        if (isset($this->roleCodesCache[$user->id])) {
            return collect($this->roleCodesCache[$user->id]);
        }

        $cached = $this->sharedCache->get($this->cacheTags($user), 'roles');

        if (is_array($cached)) {
            return collect($this->roleCodesCache[$user->id] = $cached);
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

        $resolved = array_values(array_unique($codes));
        $this->sharedCache->put($this->cacheTags($user), 'roles', $resolved);

        return collect($this->roleCodesCache[$user->id] = $resolved);
    }

    protected function resolveModuleAccessMap(User $user): Collection
    {
        if (isset($this->moduleAccessCache[$user->id])) {
            return collect($this->moduleAccessCache[$user->id]);
        }

        $cached = $this->sharedCache->get($this->cacheTags($user), 'module-access');

        if (is_array($cached)) {
            return collect($this->moduleAccessCache[$user->id] = $cached);
        }

        $modules = DB::table('security_role_module_access as access')
            ->join('security_roles as roles', 'roles.id', '=', 'access.role_id')
            ->join('security_modules as modules', 'modules.id', '=', 'access.module_id')
            ->join('security_user_roles as user_roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->where('user_roles.is_active', true)
            ->where('roles.is_active', true)
            ->whereIn('access.access_level', ['readonly', 'limited', 'full', 'placeholder'])
            ->pluck('modules.code')
            ->map(fn (string $code) => trim($code))
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $code) => [$code => true]);

        $resolved = $modules->all();
        $this->sharedCache->put($this->cacheTags($user), 'module-access', $resolved);

        return collect($this->moduleAccessCache[$user->id] = $resolved);
    }

    /** @return array<int,string> */
    private function cacheTags(User $user): array
    {
        return [
            'navigation-response',
            'navigation-response:authorization',
            $this->userCacheTag((int) $user->id),
            'navigation-response:organization:'.($user->organization_id ?: 'default'),
        ];
    }

    private function userCacheTag(int $userId): string
    {
        return "navigation-response:user:{$userId}";
    }
}
