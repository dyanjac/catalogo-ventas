<?php

namespace Modules\Commerce\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class CommerceSetting extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'brand_name',
        'company_name',
        'tagline',
        'tax_id',
        'address',
        'phone',
        'mobile',
        'support_phone',
        'logo_path',
        'email',
        'support_email',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }
}
