<?php

declare(strict_types=1);

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Enums\InventoryLedgerRolloutMode;

class InventoryLedgerRollout extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'mode',
        'reconciled_at',
        'activated_at',
        'last_summary',
    ];

    protected $casts = [
        'mode' => InventoryLedgerRolloutMode::class,
        'reconciled_at' => 'datetime',
        'activated_at' => 'datetime',
        'last_summary' => 'array',
    ];
}
