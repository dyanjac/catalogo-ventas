<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Models\SecurityRole;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->where('is_default', true)->first()
            ?? Organization::query()->orderBy('id')->first();

        if (! $organization) {
            return;
        }

        $branch = SecurityBranch::query()
            ->where('organization_id', $organization->id)
            ->where('is_default', true)
            ->first();

        $email = (string) env('ADMIN_EMAIL', 'admin@local.test');

        $user = User::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'email' => $email,
            ],
            [
                'branch_id' => $branch?->id,
                'name' => (string) env('ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make((string) env('ADMIN_PASSWORD', 'admin12345')),
                'role' => 'super_admin',
                'domain' => 'internal',
                'is_active' => true,
                'phone' => env('ADMIN_PHONE'),
            ]
        );

        $superAdminRole = SecurityRole::query()->where('code', 'super_admin')->first();

        if ($superAdminRole) {
            $user->roles()->syncWithoutDetaching([
                $superAdminRole->id => [
                    'scope' => 'all',
                    'is_active' => true,
                    'context' => json_encode(['source' => 'default_super_admin_seeder']),
                ],
            ]);
        }
    }
}
