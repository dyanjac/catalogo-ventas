<?php

namespace Modules\Security\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAuditLog extends Model
{
    use BelongsToOrganization;

    protected $table = 'security_audit_logs';

    protected $fillable = [
        'organization_id',
        'actor_user_id',
        'target_user_id',
        'event_type',
        'event_code',
        'module',
        'result',
        'message',
        'ip_address',
        'user_agent',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'context' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
