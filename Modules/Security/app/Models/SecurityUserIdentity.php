<?php

namespace Modules\Security\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityUserIdentity extends Model
{
    use BelongsToOrganization;

    protected $table = 'security_user_identities';

    protected $fillable = [
        'user_id',
        'organization_id',
        'provider_type',
        'provider_key',
        'provider_identifier',
        'provider_email',
        'provider_dn',
        'provider_payload',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
