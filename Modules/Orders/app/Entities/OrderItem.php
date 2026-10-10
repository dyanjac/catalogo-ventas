<?php

namespace Modules\Orders\Entities;

use App\Casts\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryReservationItem;
use Modules\Catalog\Entities\InventoryWarehouse;
use Modules\Catalog\Entities\Product;

class OrderItem extends Model
{
    protected $casts = [
        'quantity' => Quantity::class,
        'reserved_quantity' => Quantity::class,
        'dispatched_quantity' => Quantity::class,
        'returned_quantity' => Quantity::class,
        'unit_price' => 'decimal:6',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected $fillable = [
        'organization_id',
        'order_id',
        'product_id',
        'warehouse_id',
        'inventory_balance_id',
        'inventory_reservation_item_id',
        'currency',
        'quantity',
        'reserved_quantity',
        'dispatched_quantity',
        'returned_quantity',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'line_total',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'warehouse_id');
    }

    public function inventoryBalance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class, 'inventory_balance_id');
    }

    public function inventoryReservationItem(): BelongsTo
    {
        return $this->belongsTo(InventoryReservationItem::class, 'inventory_reservation_item_id');
    }
}
