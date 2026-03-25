<?php

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = ['organization_id', 'name', 'slug', 'description'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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
}
