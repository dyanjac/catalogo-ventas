<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Services\SecurityBranchContextService;
use Modules\Security\Services\SecurityScopeService;

class InventoryIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true, keep: true)]
    public string $search = '';

    #[Url(as: 'branch_id', history: true, keep: true)]
    public string $branchId = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBranchId(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'branchId']);
        $this->resetPage();
    }

    public function render(SecurityScopeService $scopeService, SecurityBranchContextService $branchContext)
    {
        $actor = auth()->user();
        $scopeLevel = $scopeService->scopeLevelForModule($actor, 'inventory');
        $actorBranchId = $branchContext->currentBranchId($actor);
        $effectiveBranchId = in_array($scopeLevel, ['branch', 'own'], true) ? $actorBranchId : ($this->branchId !== '' ? (int) $this->branchId : null);

        $query = ProductBranchStock::query()
            ->with(['product.category', 'branch'])
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);

                $query->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($effectiveBranchId, fn ($query) => $query->where('branch_id', $effectiveBranchId));

        $stocks = $scopeService
            ->scopeInventoryStocks($query, $actor, 'inventory')
            ->orderByDesc('stock')
            ->paginate(15);

        return view('livewire.admin.inventory-index', [
            'stocks' => $stocks,
            'branches' => SecurityBranch::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'scopeLevel' => $scopeLevel,
            'actorBranchId' => $actorBranchId,
            'effectiveBranchId' => $effectiveBranchId,
        ]);
    }
}
