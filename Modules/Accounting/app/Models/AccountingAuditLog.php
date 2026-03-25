<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingAuditLog extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'entity_type',
        'entity_id',
        'action',
        'payload',
        'user_id',
        'ip_address',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
