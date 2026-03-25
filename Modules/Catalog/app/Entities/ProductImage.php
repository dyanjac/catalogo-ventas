<?php

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = ['product_id', 'organization_id', 'product_sku', 'path', 'is_main', 'sort'];

    protected $casts = [
        'is_main' => 'boolean',
        'sort' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
