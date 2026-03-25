<?php

namespace Modules\Catalog\Entities;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Security\Models\SecurityBranch;

class InventoryTransfer extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'code',
        'source_branch_id',
        'destination_branch_id',
        'status',
        'created_by',
        'notes',
    ];

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'source_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'destination_branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class, 'transfer_id');
    }
}
