<?php

namespace Modules\Catalog\Services;

use App\Services\OrganizationContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryDocumentItem;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;

class InventoryDocumentService
{
    public function __construct(
        private readonly InventoryMovementService $movements,
        private readonly OrganizationContextService $organizationContext,
    ) {}

    public function createDraft(array $payload): InventoryDocument
    {
        $this->ensureTenantOperational();
        $organizationId = (int) $this->organizationContext->currentOrganizationId();
        $requestedOrganizationId = (int) ($payload['organization_id'] ?? $organizationId);

        if ($organizationId < 1 || $requestedOrganizationId !== $organizationId) {
            throw ValidationException::withMessages(['organization_id' => 'La organizacion del documento no coincide con el contexto activo.']);
        }

        $warehouse = InventoryWarehouse::query()
            ->where('organization_id', $organizationId)
            ->whereKey($payload['warehouse_id'])
            ->where('branch_id', $payload['branch_id'])
            ->first();

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'El almacen seleccionado no pertenece a la sucursal indicada.',
            ]);
        }

        if (! $warehouse->is_active) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'No puedes registrar guias en un almacen inactivo.',
            ]);
        }

        foreach ($payload['items'] ?? [] as $item) {
            $product = Product::query()->where('organization_id', $organizationId)->find($item['product_id']);

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'Uno de los items hace referencia a un producto inexistente.',
                ]);
            }

            $this->assertProductOperationalCoverage(
                $product,
                (int) $payload['branch_id'],
                (int) $payload['warehouse_id']
            );
        }

        $code = $payload['code'] ?? $this->nextCode((string) $payload['document_type'], $organizationId);

        $document = InventoryDocument::query()->create([
            'code' => $code,
            'document_type' => $payload['document_type'],
            'status' => $payload['status'] ?? 'draft',
            'organization_id' => $organizationId,
            'branch_id' => $payload['branch_id'],
            'warehouse_id' => $payload['warehouse_id'],
            'reason' => $payload['reason'] ?? null,
            'external_reference' => $payload['external_reference'] ?? null,
            'issued_at' => $payload['issued_at'] ?? now(),
            'created_by' => $payload['created_by'] ?? auth()->id(),
            'notes' => $payload['notes'] ?? null,
            'meta' => $payload['meta'] ?? null,
        ]);

        foreach ($payload['items'] ?? [] as $item) {
            InventoryDocumentItem::query()->create([
                'organization_id' => $organizationId,
                'document_id' => $document->id,
                'product_id' => $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'target_quantity' => isset($item['target_quantity']) ? (int) $item['target_quantity'] : null,
                'unit_cost' => $item['unit_cost'] ?? null,
                'line_total' => isset($item['unit_cost']) ? round((int) $item['quantity'] * (float) $item['unit_cost'], 4) : null,
                'notes' => $item['notes'] ?? null,
                'meta' => $item['meta'] ?? null,
            ]);
        }

        return $document->load(['items.product', 'warehouse', 'branch']);
    }

    public function confirm(int $documentId, ?int $actorId = null): InventoryDocument
    {
        $this->ensureTenantOperational();
        $organizationId = (int) $this->organizationContext->currentOrganizationId();

        if ($organizationId < 1) {
            throw ValidationException::withMessages(['organization_id' => 'La organizacion activa es obligatoria.']);
        }

        return DB::transaction(function () use ($documentId, $actorId, $organizationId): InventoryDocument {
            $document = InventoryDocument::query()
                ->where('organization_id', $organizationId)
                ->with(['items.product', 'warehouse', 'branch'])
                ->lockForUpdate()
                ->findOrFail($documentId);

            if ($document->status !== 'draft') {
                if ($document->status === 'confirmed') {
                    return $document;
                }

                throw ValidationException::withMessages([
                    'document' => 'Solo se pueden confirmar documentos en borrador.',
                ]);
            }

            $warehouse = InventoryWarehouse::query()
                ->where('organization_id', $document->organization_id)
                ->whereKey($document->warehouse_id)
                ->where('branch_id', $document->branch_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse' => 'No puedes confirmar una guia sobre un almacen inactivo.',
                ]);
            }

            $items = $document->items;

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'La guia no tiene items para confirmar.',
                ]);
            }

            ProductWarehouseStock::query()
                ->where('organization_id', $document->organization_id)
                ->where('branch_id', $document->branch_id)
                ->where('warehouse_id', $warehouse->id)
                ->whereIn('product_id', $items->pluck('product_id')->all())
                ->lockForUpdate()
                ->get();

            ProductBranchStock::query()
                ->where('organization_id', $document->organization_id)
                ->where('branch_id', $document->branch_id)
                ->whereIn('product_id', $items->pluck('product_id')->all())
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $product = $item->product;

                if (! $product instanceof Product) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los items no tiene un producto valido asociado.',
                    ]);
                }

                $this->assertProductOperationalCoverage($product, (int) $document->branch_id, (int) $warehouse->id);

                $reference = [
                    'warehouse_id' => $warehouse->id,
                    'performed_by' => $actorId ?? auth()->id(),
                    'reference_type' => InventoryDocument::class,
                    'reference_id' => $document->id,
                    'reference_code' => $document->code,
                    'idempotency_key' => 'inventory-document:'.$document->id.':item:'.$item->id.':confirm',
                    'reason' => $document->reason,
                    'notes' => $document->notes,
                    'meta' => [
                        'document_type' => $document->document_type,
                        'external_reference' => $document->external_reference,
                    ],
                ];

                if ($document->document_type === 'inbound') {
                    $this->movements->recordWarehouseInbound(
                        $product,
                        (int) $document->branch_id,
                        (int) $warehouse->id,
                        (int) $item->quantity,
                        array_merge($reference, [
                            'unit_cost' => $this->resolveInboundUnitCost($item, $product),
                        ])
                    );
                } elseif ($document->document_type === 'outbound') {
                    $this->movements->recordWarehouseOutbound(
                        $product,
                        (int) $document->branch_id,
                        (int) $warehouse->id,
                        (int) $item->quantity,
                        array_merge($reference, [
                            'unit_cost' => $this->resolveOutboundUnitCost($document, $product, $warehouse->id),
                        ])
                    );
                } elseif ($document->document_type === 'opening_stock') {
                    $this->movements->recordWarehouseOpeningStock(
                        $product,
                        (int) $document->branch_id,
                        (int) $warehouse->id,
                        (int) $item->quantity,
                        array_merge($reference, [
                            'reason_code' => 'initial_stock',
                            'unit_cost' => $this->resolveInboundUnitCost($item, $product),
                        ])
                    );
                } elseif ($document->document_type === 'stock_adjustment') {
                    if ($item->target_quantity === null) {
                        throw ValidationException::withMessages([
                            'target_quantity' => 'El ajuste requiere una cantidad objetivo.',
                        ]);
                    }

                    $this->movements->recordWarehouseAdjustment(
                        $product,
                        (int) $document->branch_id,
                        (int) $warehouse->id,
                        (int) $item->target_quantity,
                        array_merge($reference, [
                            'reason_code' => 'inventory_count',
                        ])
                    );
                } else {
                    throw ValidationException::withMessages([
                        'document_type' => 'Tipo de documento no soportado para confirmacion.',
                    ]);
                }
            }

            $document->forceFill([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => $actorId ?? auth()->id(),
            ])->save();

            return $document->fresh(['items.product', 'warehouse', 'branch', 'creator', 'confirmer']);
        });
    }

    private function nextCode(string $documentType, int $organizationId): string
    {
        $prefix = match ($documentType) {
            'inbound' => 'GIN',
            'outbound' => 'GOU',
            'opening_stock' => 'GOS',
            'stock_adjustment' => 'GAD',
            default => 'GIV',
        };

        $nextId = (int) (InventoryDocument::query()->where('organization_id', $organizationId)->max('id') ?? 0) + 1;

        return $prefix.'-'.str_pad((string) $nextId, 8, '0', STR_PAD_LEFT);
    }

    private function resolveInboundUnitCost(InventoryDocumentItem $item, Product $product): float
    {
        $unitCost = $item->unit_cost ?? $product->purchase_price ?? $product->average_price;

        if ($unitCost === null || (float) $unitCost <= 0) {
            throw ValidationException::withMessages([
                'unit_cost' => "El producto {$product->name} requiere un costo unitario valido para la guia de ingreso.",
            ]);
        }

        return round((float) $unitCost, 4);
    }

    private function resolveOutboundUnitCost(InventoryDocument $document, Product $product, int $warehouseId): float
    {
        $stock = ProductWarehouseStock::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $document->branch_id)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $unitCost = (float) ($stock?->average_cost ?? 0);

        if ($unitCost <= 0) {
            throw ValidationException::withMessages([
                'average_cost' => "El producto {$product->name} no tiene costo promedio valido en el almacen seleccionado.",
            ]);
        }

        return round($unitCost, 4);
    }

    private function ensureTenantOperational(): void
    {
        if (! $this->organizationContext->isSuspended()) {
            return;
        }

        throw ValidationException::withMessages([
            'document' => 'La organización actual está suspendida y no puede operar documentos de inventario.',
        ]);
    }

    private function assertProductOperationalCoverage(Product $product, int $branchId, int $warehouseId): void
    {
        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'product' => "El producto {$product->name} esta inactivo a nivel global.",
            ]);
        }

        $branchStock = ProductBranchStock::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->first();

        if (! $branchStock || ! $branchStock->is_active) {
            throw ValidationException::withMessages([
                'branch' => "El producto {$product->name} no esta habilitado para la sucursal seleccionada.",
            ]);
        }

        $warehouseStock = ProductWarehouseStock::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (! $warehouseStock || ! $warehouseStock->is_active) {
            throw ValidationException::withMessages([
                'warehouse' => "El producto {$product->name} no esta habilitado para el almacen seleccionado.",
            ]);
        }
    }
}
