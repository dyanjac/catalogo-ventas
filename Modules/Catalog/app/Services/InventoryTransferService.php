<?php

namespace Modules\Catalog\Services;

use App\Services\OrganizationContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Entities\InventoryTransfer;
use Modules\Catalog\Entities\InventoryTransferItem;
use Modules\Catalog\Entities\Product;

class InventoryTransferService
{
    public function __construct(
        private readonly InventoryMovementService $movements,
        private readonly ProductInventoryService $inventory,
        private readonly OrganizationContextService $organizationContext,
    ) {
    }

    public function transferProduct(
        Product $product,
        int $sourceBranchId,
        int $destinationBranchId,
        int $quantity,
        array $context = []
    ): InventoryTransfer {
        if ($sourceBranchId === $destinationBranchId) {
            throw ValidationException::withMessages([
                'transferDestinationBranchId' => 'La sucursal destino debe ser distinta a la sucursal origen.',
            ]);
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'transferQuantity' => 'La cantidad a transferir debe ser mayor a cero.',
            ]);
        }

        $available = $this->inventory->availableStock($product, $sourceBranchId);

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'transferQuantity' => "Stock insuficiente para {$product->name} en la sucursal origen. Disponible: {$available}.",
            ]);
        }

        return DB::transaction(function () use ($product, $sourceBranchId, $destinationBranchId, $quantity, $context): InventoryTransfer {
            $organizationId = $this->organizationContext->currentOrganizationId();
            $nextId = (int) (InventoryTransfer::query()->forCurrentOrganization()->max('id') ?? 0) + 1;
            $code = 'TRF-'.str_pad((string) $nextId, 8, '0', STR_PAD_LEFT);

            $transfer = InventoryTransfer::query()->create([
                'organization_id' => $organizationId,
                'code' => $code,
                'source_branch_id' => $sourceBranchId,
                'destination_branch_id' => $destinationBranchId,
                'status' => 'completed',
                'created_by' => $context['created_by'] ?? auth()->id(),
                'notes' => $context['notes'] ?? null,
            ]);

            InventoryTransferItem::query()->create([
                'organization_id' => $organizationId,
                'transfer_id' => $transfer->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);

            $this->movements->recordOutbound($product, $sourceBranchId, $quantity, [
                'reason' => 'branch_transfer_out',
                'performed_by' => $context['created_by'] ?? auth()->id(),
                'reference_type' => InventoryTransfer::class,
                'reference_id' => $transfer->id,
                'reference_code' => $transfer->code,
                'notes' => $context['notes'] ?? null,
                'meta' => [
                    'destination_branch_id' => $destinationBranchId,
                ],
            ]);

            $this->movements->recordInbound($product, $destinationBranchId, $quantity, [
                'reason' => 'branch_transfer_in',
                'performed_by' => $context['created_by'] ?? auth()->id(),
                'reference_type' => InventoryTransfer::class,
                'reference_id' => $transfer->id,
                'reference_code' => $transfer->code,
                'notes' => $context['notes'] ?? null,
                'meta' => [
                    'source_branch_id' => $sourceBranchId,
                ],
            ]);

            return $transfer->load(['sourceBranch', 'destinationBranch', 'items.product', 'creator']);
        });
    }
}
