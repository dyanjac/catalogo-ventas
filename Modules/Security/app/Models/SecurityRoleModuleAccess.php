<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SecurityRoleModuleAccess extends Pivot
{
    protected $table = 'security_role_module_access';

    protected $fillable = [
        'role_id',
        'module_id',
        'access_level',
        'navigation_visible',
    ];

    protected function casts(): array
    {
        return [
            'navigation_visible' => 'boolean',
        ];
    }
}
