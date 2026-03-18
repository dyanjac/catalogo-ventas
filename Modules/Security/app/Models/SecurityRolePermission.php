<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SecurityRolePermission extends Pivot
{
    protected $table = 'security_role_permissions';

    protected $fillable = [
        'role_id',
        'permission_id',
    ];
}
