<?php

namespace Modules\Catalog\Services;

use App\Services\OrganizationContextService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryDocumentItem;
use Modules\Catalog\Entities\InventoryLedgerRollout;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Entities\InventoryReservation;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Entities\ProductBranchStock;
use Modules\Catalog\Entities\ProductWarehouseStock;
use Modules\Catalog\Enums\InventoryDocumentStatus;
use Modules\Catalog\Enums\InventoryDocumentType;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;

class InventoryDocumentService
{
    public function __construct(
        private readonly InventoryMovementService $movements,
        private readonly OrganizationContextService $organizationContext,
        private readonly InventoryReservationService $reservations,
    ) {}

    public function createDraft(array $payload): InventoryDocument
    {
        $this->ensureTenantOperational();
        $organizationId = (int) $this->organizationContext->currentOrganizationId();
        $requestedOrganizationId = (int) ($payload['organization_id'] ?? $organizationId);

        if ($organizationId < 1 || $requestedOrganizationId !== $organizationId) {
            throw ValidationException::withMessages(['organization_id' => 'La organizacion del documento no coincide con el contexto activo.']);
        }

        $documentType = InventoryDocumentType::tryFrom((string) ($payload['document_type'] ?? ''));
        if (! $documentType) {
            throw ValidationException::withMessages(['document_type' => 'Tipo de documento de inventario no soportado.']);
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

        $idempotencyKey = (string) ($payload['idempotency_key'] ?? Str::uuid());
        $hashPayload = Arr::sortRecursive([
            'organization_id' => $organizationId,
            'document_type' => $documentType->value,
            'branch_id' => (int) $payload['branch_id'],
            'warehouse_id' => (int) $payload['warehouse_id'],
            'reservation_id' => isset($payload['reservation_id']) ? (int) $payload['reservation_id'] : null,
            'reason' => $payload['reason'] ?? null,
            'external_reference' => $payload['external_reference'] ?? null,
            'items' => $payload['items'] ?? [],
        ]);
        $payloadHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR));
        $existing = InventoryDocument::query()
            ->where('organization_id', $organizationId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing) {
            if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'La clave ya fue usada con otro documento.']);
            }

            return $existing->load(['items.product', 'warehouse', 'branch']);
        }

        try {
            return DB::transaction(function () use ($payload, $organizationId, $documentType, $idempotencyKey, $payloadHash): InventoryDocument {
                $existing = InventoryDocument::query()
                    ->where('organization_id', $organizationId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                        throw ValidationException::withMessages(['idempotency_key' => 'La clave ya fue usada con otro documento.']);
                    }

                    return $existing->load(['items.product', 'warehouse', 'branch']);
                }

                $document = InventoryDocument::query()->create([
                    'code' => $payload['code'] ?? 'INV-'.strtoupper(substr(sha1($organizationId.':'.$idempotencyKey), 0, 12)),
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'document_type' => $documentType->value,
                    'status' => InventoryDocumentStatus::Draft->value,
                    'organization_id' => $organizationId,
                    'branch_id' => $payload['branch_id'],
                    'warehouse_id' => $payload['warehouse_id'],
                    'reservation_id' => $payload['reservation_id'] ?? null,
                    'reason' => $payload['reason'] ?? null,
                    'external_reference' => $payload['external_reference'] ?? null,
                    'issued_at' => $payload['issued_at'] ?? now(),
                    'created_by' => $payload['created_by'] ?? auth()->id(),
                    'notes' => $payload['notes'] ?? null,
                    'meta' => $payload['meta'] ?? null,
                ]);

                foreach ($payload['items'] ?? [] as $item) {
                    $balanceId = InventoryBalance::query()
                        ->where('organization_id', $organizationId)
                        ->where('product_id', $item['product_id'])
                        ->where('warehouse_id', $payload['warehouse_id'])
                        ->value('id');
                    InventoryDocumentItem::query()->create([
                        'organization_id' => $organizationId,
                        'document_id' => $document->id,
                        'product_id' => $item['product_id'],
                        'inventory_balance_id' => $balanceId,
                        'quantity' => (int) $item['quantity'],
                        'target_quantity' => isset($item['target_quantity']) ? (int) $item['target_quantity'] : null,
                        'unit_cost' => $item['unit_cost'] ?? null,
                        'line_total' => isset($item['unit_cost']) ? round((int) $item['quantity'] * (float) $item['unit_cost'], 4) : null,
                        'notes' => $item['notes'] ?? null,
                        'meta' => $item['meta'] ?? null,
                    ]);
                }

                return $document->load(['items.product', 'warehouse', 'branch']);
            }, max(1, (int) config('catalog.reservations.transaction_attempts', 5)));
        } catch (QueryException $exception) {
            $existing = InventoryDocument::query()
                ->where('organization_id', $organizationId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if (! $existing) {
                throw $exception;
            }
            if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'La clave ya fue usada con otro documento.']);
            }

            return $existing->load(['items.product', 'warehouse', 'branch']);
        }
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
                ->lockForUpdate()
                ->findOrFail($documentId);

            if ($document->status !== InventoryDocumentStatus::Draft) {
                if ($document->status === InventoryDocumentStatus::Confirmed) {
                    return $document;
                }

                throw ValidationException::withMessages([
                    'document' => 'Solo se pueden confirmar documentos en borrador.',
                ]);
            }

            if (in_array($document->document_type, [
                InventoryDocumentType::Dispatch,
                InventoryDocumentType::Receipt,
                InventoryDocumentType::CustomerReturn,
                InventoryDocumentType::SupplierReturn,
            ], true)) {
                $this->lockActiveRollout($organizationId);
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

            $items = InventoryDocumentItem::query()
                ->where('organization_id', $organizationId)
                ->where('document_id', $document->id)
                ->with('product')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $document->setRelation('items', $items);
            $document->setRelation('warehouse', $warehouse);

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'La guia no tiene items para confirmar.',
                ]);
            }

            if ($document->document_type === InventoryDocumentType::Dispatch && $document->reservation_id) {
                $this->assertDispatchMatchesReservation($document);
                $this->reservations->consume(
                    (int) $document->organization_id,
                    (int) $document->reservation_id,
                    'inventory-document:'.$document->id.':consume-reservation',
                    $actorId ?? auth()->id(),
                    InventoryDocument::class,
                    (int) $document->id,
                    (string) $document->code,
                    ['document_type' => $document->document_type->value],
                );
                $movements = InventoryMovement::query()
                    ->where('organization_id', $document->organization_id)
                    ->where('reference_type', InventoryDocument::class)
                    ->where('reference_id', $document->id)
                    ->get()->groupBy('product_id');
                foreach ($items as $item) {
                    $item->forceFill(['inventory_movement_id' => $movements->get($item->product_id)?->first()?->id])->save();
                }
            } else {
                ProductBranchStock::query()
                    ->where('organization_id', $document->organization_id)
                    ->where('branch_id', $document->branch_id)
                    ->whereIn('product_id', $items->pluck('product_id')->all())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                ProductWarehouseStock::query()
                    ->where('organization_id', $document->organization_id)
                    ->where('branch_id', $document->branch_id)
                    ->where('warehouse_id', $warehouse->id)
                    ->whereIn('product_id', $items->pluck('product_id')->all())
                    ->orderBy('id')
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
                            'document_type' => $document->document_type->value,
                            'external_reference' => $document->external_reference,
                        ],
                    ];

                    $movement = null;
                    if (in_array($document->document_type, [InventoryDocumentType::Inbound, InventoryDocumentType::Receipt, InventoryDocumentType::CustomerReturn], true)) {
                        $movement = $this->movements->recordWarehouseInbound(
                            $product,
                            (int) $document->branch_id,
                            (int) $warehouse->id,
                            (int) $item->quantity,
                            array_merge($reference, [
                                'unit_cost' => $this->resolveInboundUnitCost($item, $product),
                                'reason_code' => $document->document_type === InventoryDocumentType::CustomerReturn ? 'customer_return' : 'receipt',
                            ])
                        );
                    } elseif (in_array($document->document_type, [InventoryDocumentType::Outbound, InventoryDocumentType::Dispatch, InventoryDocumentType::SupplierReturn], true)) {
                        $movement = $this->movements->recordWarehouseOutbound(
                            $product,
                            (int) $document->branch_id,
                            (int) $warehouse->id,
                            (int) $item->quantity,
                            array_merge($reference, [
                                'unit_cost' => $this->resolveOutboundUnitCost($document, $product, $warehouse->id),
                                'reason_code' => match ($document->document_type) {
                                    InventoryDocumentType::Dispatch => 'dispatch',
                                    InventoryDocumentType::SupplierReturn => 'supplier_return',
                                    default => 'other',
                                },
                            ])
                        );
                    } elseif ($document->document_type === InventoryDocumentType::OpeningStock) {
                        $movement = $this->movements->recordWarehouseOpeningStock(
                            $product,
                            (int) $document->branch_id,
                            (int) $warehouse->id,
                            (int) $item->quantity,
                            array_merge($reference, [
                                'reason_code' => 'initial_stock',
                                'unit_cost' => $this->resolveInboundUnitCost($item, $product),
                            ])
                        );
                    } elseif ($document->document_type === InventoryDocumentType::StockAdjustment) {
                        if ($item->target_quantity === null) {
                            throw ValidationException::withMessages([
                                'target_quantity' => 'El ajuste requiere una cantidad objetivo.',
                            ]);
                        }

                        $movement = $this->movements->recordWarehouseAdjustment(
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
                    $item->forceFill(['inventory_movement_id' => $movement?->id])->save();
                }
            }

            $document->forceFill([
                'status' => InventoryDocumentStatus::Confirmed->value,
                'confirmed_at' => now(),
                'confirmed_by' => $actorId ?? auth()->id(),
            ])->save();

            return $document->fresh(['items.product', 'warehouse', 'branch', 'creator', 'confirmer']);
        }, max(1, (int) config('catalog.reservations.transaction_attempts', 5)));
    }

    public function reverse(int $documentId, string $idempotencyKey, ?int $actorId = null, ?string $reason = null): InventoryDocument
    {
        $this->ensureTenantOperational();
        $organizationId = (int) $this->organizationContext->currentOrganizationId();
        $this->assertIdempotencyKey($idempotencyKey);
        $canonicalReason = $reason ?? 'document_compensation';
        $payloadHash = hash('sha256', json_encode(Arr::sortRecursive([
            'organization_id' => $organizationId,
            'document_id' => $documentId,
            'actor_id' => $actorId,
            'reason' => $canonicalReason,
        ]), JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($organizationId, $documentId, $idempotencyKey, $actorId, $canonicalReason, $payloadHash): InventoryDocument {
                $this->lockActiveRollout($organizationId);
                $original = InventoryDocument::query()
                    ->where('organization_id', $organizationId)
                    ->lockForUpdate()
                    ->findOrFail($documentId);
                $existing = InventoryDocument::query()
                    ->where('organization_id', $organizationId)
                    ->where(function ($query) use ($documentId, $idempotencyKey): void {
                        $query->where('reversal_of_id', $documentId)->orWhere('idempotency_key', $idempotencyKey);
                    })
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $this->validateReversalReplay($existing, $documentId, $idempotencyKey, $payloadHash);
                }
                if ($original->status !== InventoryDocumentStatus::Confirmed) {
                    throw ValidationException::withMessages(['document' => 'Solo un documento confirmado puede compensarse.']);
                }
                $items = InventoryDocumentItem::query()
                    ->where('organization_id', $organizationId)
                    ->where('document_id', $original->id)
                    ->with('movement')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $original->setRelation('items', $items);
                $movements = $items->pluck('movement')->filter();
                if ($movements->count() !== $items->count()) {
                    throw ValidationException::withMessages(['document' => 'El documento no tiene movimientos completos para compensar.']);
                }

                $reversal = InventoryDocument::query()->create([
                    'organization_id' => $organizationId,
                    'code' => 'REV-'.$original->id,
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'document_type' => InventoryDocumentType::Compensation->value,
                    'status' => InventoryDocumentStatus::Confirmed->value,
                    'branch_id' => $original->branch_id,
                    'warehouse_id' => $original->warehouse_id,
                    'reversal_of_id' => $original->id,
                    'reason' => $canonicalReason,
                    'issued_at' => now(),
                    'confirmed_at' => now(),
                    'created_by' => $actorId,
                    'confirmed_by' => $actorId,
                    'meta' => ['original_document_code' => $original->code],
                ]);
                foreach ($items as $item) {
                    $movement = $item->movement;
                    $compensatingMovement = $this->movements->reverse(
                        $movement,
                        $actorId,
                        $canonicalReason,
                        $idempotencyKey.':movement:'.$movement->id,
                    );
                    InventoryDocumentItem::query()->create([
                        'organization_id' => $organizationId,
                        'document_id' => $reversal->id,
                        'product_id' => $item->product_id,
                        'inventory_balance_id' => $item->inventory_balance_id,
                        'inventory_movement_id' => $compensatingMovement->id,
                        'quantity' => abs((int) $compensatingMovement->quantity),
                        'unit_cost' => $compensatingMovement->unit_cost,
                        'line_total' => $compensatingMovement->total_cost,
                        'meta' => ['reversal_of_movement_id' => $movement->id],
                    ]);
                }

                return $reversal->load(['items.movement', 'reversalOf']);
            }, max(1, (int) config('catalog.reservations.transaction_attempts', 5)));
        } catch (QueryException $exception) {
            $existing = InventoryDocument::query()
                ->where('organization_id', $organizationId)
                ->where(function ($query) use ($documentId, $idempotencyKey): void {
                    $query->where('reversal_of_id', $documentId)->orWhere('idempotency_key', $idempotencyKey);
                })
                ->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->validateReversalReplay($existing, $documentId, $idempotencyKey, $payloadHash);
        }
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

    private function lockActiveRollout(int $organizationId): void
    {
        $mode = InventoryLedgerRollout::query()
            ->where('organization_id', $organizationId)
            ->sharedLock()
            ->first()?->mode;
        if ($mode !== InventoryLedgerRolloutMode::Active) {
            throw ValidationException::withMessages(['rollout' => 'Las operaciones documentales de almacen requieren ledger active.']);
        }
    }

    private function assertIdempotencyKey(string $key): void
    {
        if ($key === '' || mb_strlen($key) > 160) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clave de idempotencia es obligatoria y admite hasta 160 caracteres.']);
        }
    }

    private function validateReversalReplay(InventoryDocument $existing, int $documentId, string $key, string $hash): InventoryDocument
    {
        if ((int) $existing->reversal_of_id !== $documentId
            || (string) $existing->idempotency_key !== $key
            || ! hash_equals((string) $existing->payload_hash, $hash)) {
            throw ValidationException::withMessages(['idempotency_key' => 'El documento ya fue compensado o la clave se uso con otro contenido.']);
        }

        return $existing->load(['items.movement', 'reversalOf']);
    }

    private function assertDispatchMatchesReservation(InventoryDocument $document): void
    {
        $reservation = InventoryReservation::query()
            ->where('organization_id', $document->organization_id)
            ->with('items.balance')
            ->findOrFail($document->reservation_id);
        $reservationItems = $reservation->items;
        if ($reservationItems->contains(fn ($item) => (int) $item->balance?->warehouse_id !== (int) $document->warehouse_id)) {
            throw ValidationException::withMessages(['reservation' => 'La reserva contiene saldos de otro almacen.']);
        }
        $reserved = $reservationItems->groupBy('product_id')->map(fn ($items) => (int) $items->sum('quantity'))->sortKeys()->all();
        $documented = $document->items->groupBy('product_id')->map(fn ($items) => (int) $items->sum('quantity'))->sortKeys()->all();
        if ($reserved !== $documented) {
            throw ValidationException::withMessages(['reservation' => 'El despacho debe coincidir exactamente con los items de la reserva.']);
        }
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
