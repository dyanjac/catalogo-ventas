<?php

namespace Modules\Commerce\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class CommerceSetting extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'company_name',
        'tax_id',
        'address',
        'phone',
        'mobile',
        'logo_path',
        'email',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }
}
