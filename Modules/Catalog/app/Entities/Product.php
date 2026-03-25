<?php

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'category_id',
        'unit_measure_id',
        'name',
        'sku',
        'slug',
        'description',
        'tax_affectation',
        'uses_series',
        'account',
        'requires_accounting_entry',
        'account_revenue',
        'account_receivable',
        'account_inventory',
        'account_cogs',
        'account_tax',
        'purchase_price',
        'sale_price',
        'wholesale_price',
        'average_price',
        'price',
        'stock',
        'min_stock',
        'image',
        'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'average_price' => 'decimal:2',
        'price' => 'decimal:2',
        'uses_series' => 'boolean',
        'requires_accounting_entry' => 'boolean',
        'is_active' => 'boolean',
        'stock' => 'integer',
        'min_stock' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitMeasure::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function branchStocks(): HasMany
    {
        return $this->hasMany(ProductBranchStock::class);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(ProductWarehouseStock::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function inventoryDocumentItems(): HasMany
    {
        return $this->hasMany(InventoryDocumentItem::class);
    }

    public function mainImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_main', true)->orderBy('sort')->orderByDesc('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $field ??= $this->getRouteKeyName();

        return $query->forCurrentOrganization()->where($field, $value);
    }

    public function getDisplayPriceAttribute(): ?string
    {
        return $this->sale_price ?? $this->price;
    }

    public function getPrimaryImagePathAttribute(): ?string
    {
        if ($this->relationLoaded('mainImage') && $this->mainImage?->path) {
            return $this->mainImage->path;
        }

        $mainImage = $this->mainImage()->first();

        return $mainImage?->path ?: $this->image;
    }

    public function getEffectiveStockAttribute(): int
    {
        if ($this->relationLoaded('warehouseStocks') && $this->warehouseStocks->isNotEmpty()) {
            return (int) $this->warehouseStocks->sum('stock');
        }

        if ($this->relationLoaded('branchStocks') && $this->branchStocks->isNotEmpty()) {
            return (int) ($this->branchStocks->first()?->stock ?? 0);
        }

        return (int) ($this->stock ?? 0);
    }

    public function getEffectiveMinStockAttribute(): int
    {
        if ($this->relationLoaded('warehouseStocks') && $this->warehouseStocks->isNotEmpty()) {
            return (int) $this->warehouseStocks->sum('min_stock');
        }

        if ($this->relationLoaded('branchStocks') && $this->branchStocks->isNotEmpty()) {
            return (int) ($this->branchStocks->first()?->min_stock ?? 0);
        }

        return (int) ($this->min_stock ?? 0);
    }
}
