<?php

declare(strict_types=1);

namespace Modules\Catalog\Entities;

use App\Casts\Quantity;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Enums\InventoryLocationType;
use Modules\Security\Models\SecurityBranch;

class InventoryBalance extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'product_id',
        'branch_id',
        'warehouse_id',
        'location_type',
        'location_key',
        'physical_stock',
        'reserved_stock',
        'in_transit_stock',
        'min_stock',
        'average_cost',
        'last_cost',
        'version',
        'reservation_version',
        'transit_version',
        'is_active',
    ];

    protected $casts = [
        'location_type' => InventoryLocationType::class,
        'physical_stock' => Quantity::class,
        'reserved_stock' => Quantity::class,
        'in_transit_stock' => Quantity::class,
        'min_stock' => Quantity::class,
        'average_cost' => 'decimal:6',
        'last_cost' => 'decimal:6',
        'version' => 'integer',
        'reservation_version' => 'integer',
        'transit_version' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'branch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'warehouse_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(InventoryReservationItem::class, 'inventory_balance_id');
    }

    public function availableStock(): int|string
    {
        $available = Decimal::sub($this->physical_stock, $this->reserved_stock, 4);

        return Decimal::compare($available, 0, 4) > 0 ? $available : 0;
    }

    public static function locationKey(int $branchId, ?int $warehouseId): string
    {
        return $warehouseId ? 'warehouse:'.$warehouseId : 'unallocated:'.$branchId;
    }
}
