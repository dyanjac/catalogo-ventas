<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Entities\Product;

class TransportGuideItem extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'transport_guide_id', 'line_number', 'product_id', 'code', 'description',
        'quantity', 'unit_code', 'sunat_product_code',
    ];

    protected $casts = ['quantity' => 'decimal:4'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Los items documentales de una GRE son inmutables.'));
        static::deleting(fn () => throw new \LogicException('Los items documentales de una GRE no se eliminan.'));
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(TransportGuide::class, 'transport_guide_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
