<?php

namespace Modules\Orders\Entities;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\OrganizationContextService;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Models\BillingDocument;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryReservation;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Orders\Enums\OrderWarehouseStatus;
use Modules\Security\Models\SecurityBranch;

class Order extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'user_id',
        'organization_id',
        'branch_id',
        'sales_channel',
        'idempotency_key',
        'payload_hash',
        'warehouse_id',
        'inventory_reservation_id',
        'dispatch_document_id',
        'return_document_id',
        'series',
        'order_number',
        'status',
        'warehouse_status',
        'reservation_version',
        'currency',
        'subtotal',
        'discount',
        'shipping',
        'tax',
        'total',
        'shipping_address',
        'payment_method',
        'payment_status',
        'paid_at',
        'transaction_id',
        'observations',
        'reserved_at',
        'dispatch_requested_at',
        'dispatched_at',
        'return_requested_at',
        'returned_at',
        'cancelled_at',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'paid_at' => 'datetime',
        'warehouse_status' => OrderWarehouseStatus::class,
        'reservation_version' => 'integer',
        'reserved_at' => 'datetime',
        'dispatch_requested_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'return_requested_at' => 'datetime',
        'returned_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'warehouse_id');
    }

    public function inventoryReservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class, 'inventory_reservation_id');
    }

    public function dispatchDocument(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'dispatch_document_id');
    }

    public function returnDocument(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'return_document_id');
    }

    public function billingDocuments(): HasMany
    {
        return $this->hasMany(BillingDocument::class, 'order_id');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $field ??= $this->getRouteKeyName();
        $organizationId = app(OrganizationContextService::class)->currentOrganizationId();

        if ($organizationId) {
            return $query->where('organization_id', $organizationId)->where($field, $value);
        }

        return $query->whereKey(0);
    }
}
