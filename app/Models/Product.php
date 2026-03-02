<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{   
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'category_id',
        'unit_measure_id',
        'name',
        'sku',
        'slug',
        'description',
        'tax_affectation',
        'uses_series',
        'account',
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
        'is_active' => 'boolean',
        'stock' => 'integer',
        'min_stock' => 'integer',
    ];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function unitMeasure(): BelongsTo { return $this->belongsTo(UnitMeasure::class); }
    public function images(): HasMany { return $this->hasMany(ProductImage::class); }
    public function mainImage(): HasOne { return $this->hasOne(ProductImage::class)->where('is_main', true)->orderBy('sort')->orderByDesc('id'); }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string { return 'slug'; }

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
}
