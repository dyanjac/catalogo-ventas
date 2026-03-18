<?php

namespace Modules\Security\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SecurityRole extends Model
{
    protected $table = 'security_roles';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(SecurityPermission::class, 'security_role_permissions', 'role_id', 'permission_id')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'security_user_roles', 'role_id', 'user_id')
            ->withPivot(['scope', 'is_active', 'context'])
            ->withTimestamps();
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(SecurityModule::class, 'security_role_module_access', 'role_id', 'module_id')
            ->withPivot(['access_level', 'navigation_visible'])
            ->withTimestamps();
    }
}
