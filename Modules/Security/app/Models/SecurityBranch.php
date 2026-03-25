<?php

namespace Modules\Security\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Models\BillingDocument;
use Modules\Orders\Entities\Order;

class SecurityBranch extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'city',
        'address',
        'phone',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'branch_id');
    }

    public function billingDocuments(): HasMany
    {
        return $this->hasMany(BillingDocument::class, 'branch_id');
    }
}
