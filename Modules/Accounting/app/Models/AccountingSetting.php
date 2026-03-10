<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingSetting extends Model
{
    protected $fillable = [
        'fiscal_year',
        'fiscal_year_start_month',
        'default_currency',
        'period_closure_enabled',
        'auto_post_entries',
    ];

    protected $casts = [
        'period_closure_enabled' => 'boolean',
        'auto_post_entries' => 'boolean',
    ];
}
