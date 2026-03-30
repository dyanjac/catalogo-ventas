<?php

namespace App\Livewire\Admin;

use App\Services\OrganizationContextService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Services\SecurityAuditService;
use Modules\Security\Services\SecurityAuthorizationService;
use Modules\Security\Services\SecurityBranchContextService;
use Modules\Security\Services\SecurityScopeService;

class InventoryWarehouseManagement extends Component
{
    use WithPagination;

    #[Url(as: 'search', history: true, keep: true)]
    public string $search = '';

    public ?int $selectedWarehouseId = null;

    public string $branch_id = '';

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    public bool $is_default = false;

    public ?string $flashMessage = null;

    public string $flashTone = 'success';

    public function mount(SecurityBranchContextService $branchContext, SecurityScopeService $scopeService): void
    {
        $this->resetForm();

        $actor = auth()->user();
        $scopeLevel = $scopeService->scopeLevelForModule($actor, 'inventory');
        $actorBranchId = $branchContext->currentBranchId($actor);

        if (in_array($scopeLevel, ['branch', 'own'], true) && $actorBranchId) {
            $this->branch_id = (string) $actorBranchId;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function createWarehouse(SecurityBranchContextService $branchContext, SecurityScopeService $scopeService): void
    {
        $this->selectedWarehouseId = null;
        $this->resetForm();

        $actor = auth()->user();
        $scopeLevel = $scopeService->scopeLevelForModule($actor, 'inventory');
        $actorBranchId = $branchContext->currentBranchId($actor);

        if (in_array($scopeLevel, ['branch', 'own'], true) && $actorBranchId) {
            $this->branch_id = (string) $actorBranchId;
        }
    }

    public function selectWarehouse(int $warehouseId, SecurityScopeService $scopeService): void
    {
        $warehouse = InventoryWarehouse::query()->forCurrentOrganization()->with('branch')->findOrFail($warehouseId);

        abort_unless($scopeService->canAccessInventoryWarehouse(auth()->user(), $warehouse, 'inventory'), 403);

        $this->selectedWarehouseId = $warehouse->id;
        $this->branch_id = (string) $warehouse->branch_id;
        $this->code = $warehouse->code;
        $this->name = $warehouse->name;
        $this->description = (string) ($warehouse->description ?? '');
        $this->is_active = (bool) $warehouse->is_active;
        $this->is_default = (bool) $warehouse->is_default;
        $this->flashMessage = null;
    }

    public function save(
        SecurityAuthorizationService $authorization,
        SecurityAuditService $audit,
        SecurityScopeService $scopeService,
        SecurityBranchContextService $branchContext,
        OrganizationContextService $organizationContext,
    ): void {
        $actor = auth()->user();
        $organizationId = $organizationContext->currentOrganizationId();

        abort_unless($authorization->hasPermission($actor, 'inventory.warehouses.update'), 403);

        if ($organizationContext->isSuspended()) {
            throw ValidationException::withMessages([
                'branch_id' => 'La organización actual está suspendida y no puede administrar almacenes.',
            ]);
        }

        $validated = $this->validate([
            'branch_id' => ['required', 'integer', Rule::exists('security_branches', 'id')->where('organization_id', $organizationId)],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('inventory_warehouses', 'code')
                    ->where(fn ($query) => $query->where('organization_id', $organizationId)->where('branch_id', (int) $this->branch_id))
                    ->ignore($this->selectedWarehouseId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ]);

        $scopeLevel = $scopeService->scopeLevelForModule($actor, 'inventory');
        $actorBranchId = $branchContext->currentBranchId($actor);

        if (in_array($scopeLevel, ['branch', 'own'], true) && (int) $validated['branch_id'] !== (int) $actorBranchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Tu perfil solo puede administrar almacenes de la sucursal asignada.',
            ]);
        }

        if ($this->selectedWarehouseId && ! (bool) $validated['is_active']) {
            $warehouseWithRelations = InventoryWarehouse::query()->forCurrentOrganization()->findOrFail($this->selectedWarehouseId);

            if (ProductWarehouseStock::query()->forCurrentOrganization()->where('warehouse_id', $warehouseWithRelations->id)->where('stock', '>', 0)->exists()) {
                throw ValidationException::withMessages([
                    'is_active' => 'No puedes desactivar un almacen mientras existan productos con stock disponible.',
                ]);
            }

            if (InventoryDocument::query()->forCurrentOrganization()->where('warehouse_id', $warehouseWithRelations->id)->where('status', 'draft')->exists()) {
                throw ValidationException::withMessages([
                    'is_active' => 'No puedes desactivar un almacen mientras existan guias en borrador asociadas.',
                ]);
            }
        }

        if ($validated['is_default']) {
            InventoryWarehouse::query()
                ->forCurrentOrganization()
                ->where('branch_id', (int) $validated['branch_id'])
                ->update(['is_default' => false]);
        }

        $warehouse = InventoryWarehouse::query()->updateOrCreate(
            ['id' => $this->selectedWarehouseId],
            [
                'organization_id' => $organizationId,
                'branch_id' => (int) $validated['branch_id'],
                'code' => strtoupper(trim($validated['code'])),
                'name' => trim($validated['name']),
                'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                'is_active' => (bool) $validated['is_active'],
                'is_default' => (bool) $validated['is_default'],
            ]
        );

        $audit->log(
            eventType: 'authorization',
            eventCode: 'inventory.warehouse.saved',
            result: 'success',
            message: 'Se guardo un almacen de inventario.',
            actor: $actor,
            target: $warehouse,
            module: 'inventory',
            context: [
                'branch_id' => $warehouse->branch_id,
                'code' => $warehouse->code,
                'is_default' => $warehouse->is_default,
            ],
        );

        $this->selectWarehouse($warehouse->id, $scopeService);
        $this->flashTone = 'success';
        $this->flashMessage = 'Almacen guardado correctamente.';
    }

    public function render(SecurityScopeService $scopeService, SecurityBranchContextService $branchContext, SecurityAuthorizationService $authorization)
    {
        $actor = auth()->user();
        $scopeLevel = $scopeService->scopeLevelForModule($actor, 'inventory');
        $actorBranchId = $branchContext->currentBranchId($actor);

        $branches = $scopeService->scopeBranches(
            SecurityBranch::query()->where('is_active', true),
            $actor,
            'inventory'
        )
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $warehouses = $scopeService->scopeInventoryWarehouses(
            InventoryWarehouse::query()
                ->with('branch')
                ->withCount(['stocks', 'documents'])
                ->withSum('stocks as stock_total', 'stock')
                ->withSum('stocks as min_stock_total', 'min_stock')
                ->when(trim($this->search) !== '', function ($query): void {
                    $search = trim($this->search);
                    $query->where(function ($subQuery) use ($search): void {
                        $subQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhereHas('branch', fn ($branchQuery) => $branchQuery->where('name', 'like', "%{$search}%"));
                    });
                }),
            $actor,
            'inventory'
        )
            ->orderBy('branch_id')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(10);

        if ($warehouses->count() > 0 && ! collect($warehouses->items())->contains('id', $this->selectedWarehouseId)) {
            $this->selectWarehouse((int) $warehouses->items()[0]->id, $scopeService);
        }

        return view('livewire.admin.inventory-warehouse-management', [
            'branches' => $branches,
            'warehouses' => $warehouses,
            'canManageWarehouses' => $authorization->hasPermission($actor, 'inventory.warehouses.update'),
            'canManageBranches' => $authorization->hasPermission($actor, 'security.branches.update'),
            'scopeLevel' => $scopeLevel,
        ]);
    }

    private function resetForm(): void
    {
        $this->branch_id = '';
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->is_active = true;
        $this->is_default = false;
    }
}

