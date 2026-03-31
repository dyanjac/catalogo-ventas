<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\OrganizationContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Modules\Orders\Entities\Order;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Models\SecurityRole;

class User extends Authenticatable
{
    use BelongsToOrganization;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'document_type',
        'document_number',
        'city',
        'address',
        'is_active',
        'role',
        'guid',
        'domain',
        'organization_id',
        'branch_id',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return DB::table('security_user_roles as user_roles')
            ->join('security_roles as roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)
            ->where('user_roles.is_active', true)
            ->where('roles.is_active', true)
            ->where('roles.code', 'super_admin')
            ->exists();
    }

    public function roleCodes(): array
    {
        return DB::table('security_user_roles as user_roles')
            ->join('security_roles as roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->id)
            ->where('user_roles.is_active', true)
            ->where('roles.is_active', true)
            ->pluck('roles.code')
            ->all();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SecurityBranch::class, 'branch_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(SecurityRole::class, 'security_user_roles', 'user_id', 'role_id')
            ->withPivot(['scope', 'is_active', 'context'])
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        $field ??= $this->getRouteKeyName();
        $organizationId = app(OrganizationContextService::class)->currentOrganizationId();

        if ($organizationId) {
            return $query->where('organization_id', $organizationId)->where($field, $value);
        }

        return $query->whereKey(0);
    }
}
