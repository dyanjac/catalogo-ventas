<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SecurityPermission extends Model
{
    protected $table = 'security_permissions';

    protected $fillable = [
        'module_id',
        'resource',
        'action',
        'code',
        'description',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(SecurityModule::class, 'module_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(SecurityRole::class, 'security_role_permissions', 'permission_id', 'role_id')
            ->withTimestamps();
    }
}
