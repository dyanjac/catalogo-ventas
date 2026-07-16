<?php

declare(strict_types=1);

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReconciliationIssue extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'run_id',
        'organization_id',
        'product_id',
        'branch_id',
        'warehouse_id',
        'issue_type',
        'severity',
        'expected_value',
        'actual_value',
        'context',
    ];

    protected $casts = [
        'expected_value' => 'decimal:4',
        'actual_value' => 'decimal:4',
        'context' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(InventoryReconciliationRun::class, 'run_id');
    }
}
