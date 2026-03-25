<?php

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDocumentItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'document_id',
        'organization_id',
        'product_id',
        'quantity',
        'unit_cost',
        'line_total',
        'notes',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:4',
        'line_total' => 'decimal:4',
        'meta' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'document_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
