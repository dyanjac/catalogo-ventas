<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Enums\ProductAccountingTreatment;

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
        'product_accounting_treatment',
        'default_account_revenue',
        'default_account_receivable',
        'default_account_inventory',
        'default_account_cogs',
        'default_account_tax',
        'default_account_cash',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'period_closure_enabled' => 'boolean',
        'auto_post_entries' => 'boolean',
        'product_accounting_treatment' => ProductAccountingTreatment::class,
    ];
}
