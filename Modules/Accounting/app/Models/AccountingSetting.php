<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AccountingSetting extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'fiscal_year',
        'fiscal_year_start_month',
        'default_currency',
        'period_closure_enabled',
        'auto_post_entries',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'period_closure_enabled' => 'boolean',
        'auto_post_entries' => 'boolean',
    ];
}
