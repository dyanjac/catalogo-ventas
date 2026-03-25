<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Entities\Product;
use Modules\Orders\Entities\Order;

class AccountingEntryLine extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'accounting_entry_id',
        'account_code',
        'account_name',
        'debit',
        'credit',
        'line_description',
        'cost_center_id',
        'order_id',
        'product_id',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(AccountingCostCenter::class, 'cost_center_id');
    }
}
