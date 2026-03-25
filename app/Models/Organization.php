<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Models\BillingSetting;
use Modules\Commerce\Entities\CommerceSetting;
use Modules\Orders\Entities\Order;
use Modules\Security\Models\SecurityBranch;

class Organization extends Model
{
    protected $fillable = [
        'code',
        'name',
        'slug',
        'tax_id',
        'status',
        'environment',
        'is_default',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'settings_json' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(SecurityBranch::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function commerceSettings(): HasMany
    {
        return $this->hasMany(CommerceSetting::class);
    }

    public function billingSettings(): HasMany
    {
        return $this->hasMany(BillingSetting::class);
    }
}
