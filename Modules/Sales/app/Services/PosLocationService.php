<?php

namespace Modules\Sales\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Services\SecurityScopeService;

class PosLocationService
{
    public function __construct(private readonly SecurityScopeService $scope) {}

    public function branches(?User $actor): Collection
    {
        $catalogBranches = $this->scope->scopeBranches(SecurityBranch::query()->forCurrentOrganization(), $actor, 'catalog')
            ->where('is_active', true)->select('id');

        return $this->scope->scopeBranches(SecurityBranch::query()->forCurrentOrganization(), $actor, 'sales')
            ->where('is_active', true)->whereIn('id', $catalogBranches)
            ->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']);
    }

    public function assertBranch(?User $actor, int $branchId): SecurityBranch
    {
        $branch = $this->branches($actor)->firstWhere('id', $branchId);
        if (! $branch) {
            throw ValidationException::withMessages(['branch_id' => 'La sucursal no está disponible para esta venta.']);
        }

        return $branch;
    }

    public function warehouses(int $branchId): Collection
    {
        return InventoryWarehouse::query()->forCurrentOrganization()
            ->where('branch_id', $branchId)->where('is_active', true)
            ->orderByDesc('is_default')->orderBy('name')->get(['id', 'branch_id', 'name', 'is_default']);
    }

    public function assertWarehouse(int $branchId, int $warehouseId): InventoryWarehouse
    {
        $warehouse = $this->warehouses($branchId)->firstWhere('id', $warehouseId);
        if (! $warehouse) {
            throw ValidationException::withMessages(['warehouse_id' => 'El almacén no está activo en la sucursal seleccionada.']);
        }

        return $warehouse;
    }
}
