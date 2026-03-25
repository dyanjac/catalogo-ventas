<?php

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitMeasure extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = ['organization_id', 'name'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
