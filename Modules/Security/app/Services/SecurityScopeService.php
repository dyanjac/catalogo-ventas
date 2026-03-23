<?php

namespace Modules\Security\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\BillingDocument;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Orders\Entities\Order;

class SecurityScopeService
{
    public function __construct(private readonly SecurityBranchContextService $branchContext)
    {
    }

    public function scopeLevelForModule(?User $user, string $moduleCode): string
    {
        if (! $user) {
            return 'none';
        }

        if (app(SecurityAuthorizationService::class)->hasRole($user, 'super_admin')) {
            return 'all';
        }

        $scopes = DB::table('security_user_roles as user_roles')
            ->join('security_roles as roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('security_role_module_access as access', 'access.role_id', '=', 'roles.id')
            ->join('security_modules as modules', 'modules.id', '=', 'access.module_id')
            ->where('user_roles.user_id', $user->id)
            ->where('user_roles.is_active', true)
            ->where('roles.is_active', true)
            ->where('modules.code', $moduleCode)
            ->whereIn('access.access_level', ['readonly', 'limited', 'full', 'placeholder'])
            ->pluck('user_roles.scope')
            ->map(fn ($scope) => in_array($scope, ['all', 'branch', 'own'], true) ? $scope : 'all')
            ->all();

        if (in_array('all', $scopes, true)) {
            return 'all';
        }

        if (in_array('branch', $scopes, true)) {
            return 'branch';
        }

        if (in_array('own', $scopes, true)) {
            return 'own';
        }

        return 'none';
    }

    public function scopeUsers(Builder $query, ?User $actor, string $moduleCode = 'customers'): Builder
    {
        $scope = $this->scopeLevelForModule($actor, $moduleCode);

        if ($scope === 'none') {
            return $query->whereKey(0);
        }

        if ($scope === 'own') {
            return $query->whereKey($actor?->id ?? 0);
        }

        if ($scope === 'branch') {
            $branchId = $this->actorBranchId($actor);

            return $branchId ? $query->where('branch_id', $branchId) : $query->whereKey(0);
        }

        return $query;
    }

    public function scopeOrders(Builder $query, ?User $actor, string $moduleCode = 'sales'): Builder
    {
        $scope = $this->scopeLevelForModule($actor, $moduleCode);

        if ($scope === 'none') {
            return $query->whereKey(0);
        }

        if ($scope === 'own') {
            return $query->where('user_id', $actor?->id ?? 0);
        }

        if ($scope === 'branch') {
            $branchId = $this->actorBranchId($actor);

            return $branchId ? $query->where('branch_id', $branchId) : $query->whereKey(0);
        }

        return $query;
    }

    public function scopeBillingDocuments(Builder $query, ?User $actor, string $moduleCode = 'billing'): Builder
    {
        $scope = $this->scopeLevelForModule($actor, $moduleCode);

        if ($scope === 'none') {
            return $query->whereKey(0);
        }

        if ($scope === 'own') {
            return $query->whereHas('order', fn (Builder $order) => $order->where('user_id', $actor?->id ?? 0));
        }

        if ($scope === 'branch') {
            $branchId = $this->actorBranchId($actor);

            return $branchId ? $query->where('branch_id', $branchId) : $query->whereKey(0);
        }

        return $query;
    }

    public function scopeProducts(Builder $query, ?User $actor, string $moduleCode = 'catalog'): Builder
    {
        $scope = $this->scopeLevelForModule($actor, $moduleCode);

        if ($scope === 'none') {
            return $query->whereKey(0);
        }

        if (in_array($scope, ['own', 'branch'], true)) {
            $branchId = $this->actorBranchId($actor);

            return $branchId
                ? $query->whereHas('branchStocks', fn (Builder $stock) => $stock->where('branch_id', $branchId)->where('is_active', true))
                : $query->whereKey(0);
        }

        return $query;
    }

    public function scopeInventoryStocks(Builder $query, ?User $actor, string $moduleCode = 'inventory'): Builder
    {
        return $this->applyInventoryBranchScope($query, $actor, $moduleCode);
    }

    public function scopeInventoryMovements(Builder $query, ?User $actor, string $moduleCode = 'inventory'): Builder
    {
        return $this->applyInventoryBranchScope($query, $actor, $moduleCode);
    }

    public function scopeInventoryDocuments(Builder $query, ?User $actor, string $moduleCode = 'inventory'): Builder
    {
        return $this->applyInventoryBranchScope($query, $actor, $moduleCode);
    }

    public function scopeInventoryWarehouses(Builder $query, ?User $actor, string $moduleCode = 'inventory'): Builder
    {
        return $this->applyInventoryBranchScope($query, $actor, $moduleCode);
    }

    public function canAccessUser(?User $actor, User $target, string $moduleCode = 'customers'): bool
    {
        return $this->scopeUsers(User::query(), $actor, $moduleCode)->whereKey($target->id)->exists();
    }

    public function canAccessOrder(?User $actor, Order $order, string $moduleCode = 'sales'): bool
    {
        return $this->scopeOrders(Order::query(), $actor, $moduleCode)->whereKey($order->id)->exists();
    }

    public function canAccessBillingDocument(?User $actor, BillingDocument $document, string $moduleCode = 'billing'): bool
    {
        return $this->scopeBillingDocuments(BillingDocument::query(), $actor, $moduleCode)->whereKey($document->id)->exists();
    }

    public function canAccessProduct(?User $actor, Product $product, string $moduleCode = 'catalog'): bool
    {
        return $this->scopeProducts(Product::query(), $actor, $moduleCode)->whereKey($product->id)->exists();
    }

    public function canAccessInventoryStock(?User $actor, ProductBranchStock|ProductWarehouseStock $stock, string $moduleCode = 'inventory'): bool
    {
        return $this->scopeInventoryStocks($stock::query(), $actor, $moduleCode)->whereKey($stock->id)->exists();
    }

    public function canAccessInventoryMovement(?User $actor, InventoryMovement $movement, string $moduleCode = 'inventory'): bool
    {
        return $this->scopeInventoryMovements(InventoryMovement::query(), $actor, $moduleCode)->whereKey($movement->id)->exists();
    }

    public function canAccessInventoryDocument(?User $actor, InventoryDocument $document, string $moduleCode = 'inventory'): bool
    {
        return $this->scopeInventoryDocuments(InventoryDocument::query(), $actor, $moduleCode)->whereKey($document->id)->exists();
    }

    public function canAccessInventoryWarehouse(?User $actor, InventoryWarehouse $warehouse, string $moduleCode = 'inventory'): bool
    {
        return $this->scopeInventoryWarehouses(InventoryWarehouse::query(), $actor, $moduleCode)->whereKey($warehouse->id)->exists();
    }

    public function branchModeIsDegraded(string $moduleCode): bool
    {
        return ! in_array($moduleCode, ['customers', 'sales', 'billing', 'catalog', 'inventory'], true);
    }

    private function applyInventoryBranchScope(Builder $query, ?User $actor, string $moduleCode): Builder
    {
        $scope = $this->scopeLevelForModule($actor, $moduleCode);

        if ($scope === 'none') {
            return $query->whereKey(0);
        }

        if (in_array($scope, ['own', 'branch'], true)) {
            $branchId = $this->actorBranchId($actor);

            return $branchId ? $query->where('branch_id', $branchId) : $query->whereKey(0);
        }

        return $query;
    }

    private function actorBranchId(?User $actor): ?int
    {
        return $this->branchContext->currentBranchId($actor);
    }
}
