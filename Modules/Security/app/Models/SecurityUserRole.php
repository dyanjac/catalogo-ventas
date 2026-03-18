<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SecurityUserRole extends Pivot
{
    protected $table = 'security_user_roles';

    protected $fillable = [
        'user_id',
        'role_id',
        'scope',
        'is_active',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'context' => 'array',
        ];
    }
}
