<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityModule extends Model
{
    protected $table = 'security_modules';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'navigation_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'navigation_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(SecurityPermission::class, 'module_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(SecurityRole::class, 'security_role_module_access', 'module_id', 'role_id')
            ->withPivot(['access_level', 'navigation_visible'])
            ->withTimestamps();
    }
}
