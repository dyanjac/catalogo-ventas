<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Models\AccountingSetting;
use Modules\AdminTheme\Models\AdminThemeSetting;
use Modules\Billing\Models\BillingSetting;
use Modules\Commerce\Entities\CommerceSetting;
use Modules\Security\Models\SecurityAuthSetting;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Models\SecurityRole;
use RuntimeException;

class OrganizationProvisioningService
{
    /**
     * @param array<string,mixed> $data
     * @return array{organization:Organization,branch:SecurityBranch,admin:User,password:string}
     */
    public function provisionDemoOrganization(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $organizationName = trim((string) $data['organization_name']);
            $brandName = $this->nullableString($data['brand_name'] ?? null) ?? $organizationName;
            $contactEmail = Str::lower(trim((string) $data['contact_email']));
            $supportEmail = $this->nullableString($data['support_email'] ?? null) ?? $contactEmail;
            $supportPhone = $this->nullableString($data['support_phone'] ?? null) ?? $this->nullableString($data['phone'] ?? null);
            $tagline = $this->nullableString($data['tagline'] ?? null) ?? ('Panel administrativo de '.$brandName);

            $organization = Organization::query()->create([
                'code' => Str::upper(trim((string) $data['organization_code'])),
                'name' => $organizationName,
                'slug' => Str::slug((string) $data['organization_slug']),
                'tax_id' => $this->nullableString($data['tax_id'] ?? null),
                'status' => 'active',
                'environment' => 'demo',
                'is_default' => false,
                'settings_json' => [
                    'provisioned_via' => (string) ($data['provisioned_via'] ?? 'admin_quick_onboarding'),
                    'provisioned_at' => now()->toDateTimeString(),
                ],
            ]);

            $branch = SecurityBranch::query()->create([
                'organization_id' => $organization->id,
                'code' => $this->resolveBranchCode((string) ($data['branch_name'] ?? 'Sucursal Principal')),
                'name' => trim((string) ($data['branch_name'] ?? 'Sucursal Principal')),
                'city' => $this->nullableString($data['city'] ?? null),
                'address' => $this->nullableString($data['address'] ?? null),
                'phone' => $this->nullableString($data['phone'] ?? null),
                'is_active' => true,
                'is_default' => true,
            ]);

            $generatedPassword = Str::password(14);

            $admin = User::query()->create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => trim((string) $data['admin_name']),
                'email' => Str::lower(trim((string) $data['admin_email'])),
                'phone' => $this->nullableString($data['admin_phone'] ?? null),
                'address' => $this->nullableString($data['address'] ?? null),
                'city' => $this->nullableString($data['city'] ?? null),
                'role' => 'super_admin',
                'domain' => 'internal',
                'password' => $generatedPassword,
                'is_active' => true,
            ]);

            $superAdminRole = SecurityRole::query()->where('code', 'super_admin')->first();

            if (! $superAdminRole) {
                throw new RuntimeException('No existe el rol super_admin para asignar al usuario inicial.');
            }

            $admin->roles()->syncWithoutDetaching([
                $superAdminRole->id => [
                    'scope' => 'all',
                    'is_active' => true,
                    'context' => json_encode(['source' => 'organization_provisioning']),
                ],
            ]);

            CommerceSetting::query()->create([
                'organization_id' => $organization->id,
                'brand_name' => $brandName,
                'company_name' => $organization->name,
                'tagline' => $tagline,
                'tax_id' => $organization->tax_id,
                'address' => $this->nullableString($data['address'] ?? null),
                'phone' => $this->nullableString($data['phone'] ?? null),
                'mobile' => $this->nullableString($data['phone'] ?? null),
                'support_phone' => $supportPhone,
                'email' => $contactEmail,
                'support_email' => $supportEmail,
            ]);

            BillingSetting::query()->create([
                'organization_id' => $organization->id,
                'enabled' => false,
                'country' => 'PE',
                'provider' => 'sunat_greenter',
                'environment' => 'beta',
                'dispatch_mode' => 'sync',
                'queue_connection' => null,
                'queue_name' => null,
                'provider_credentials' => [],
                'invoice_series' => 'F001',
                'receipt_series' => 'B001',
                'credit_note_series' => 'FC01',
                'debit_note_series' => 'FD01',
                'default_invoice_operation_code' => '01',
                'default_receipt_operation_code' => '01',
            ]);

            AccountingSetting::query()->create([
                'organization_id' => $organization->id,
                'fiscal_year' => (int) now()->format('Y'),
                'fiscal_year_start_month' => 1,
                'default_currency' => 'PEN',
                'period_closure_enabled' => false,
                'auto_post_entries' => false,
            ]);

            $this->seedDefaultAdminPalette($organization->id);
            $this->seedDefaultSecurityAuthBranding($organization->id, $brandName, $tagline);

            return [
                'organization' => $organization,
                'branch' => $branch,
                'admin' => $admin,
                'password' => $generatedPassword,
            ];
        });
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateOrganizationProfile(Organization $organization, array $data): Organization
    {
        return DB::transaction(function () use ($organization, $data): Organization {
            $organization->forceFill([
                'name' => trim((string) $data['organization_name']),
                'code' => Str::upper(trim((string) $data['organization_code'])),
                'slug' => Str::slug((string) $data['organization_slug']),
                'tax_id' => $this->nullableString($data['tax_id'] ?? null),
            ])->save();

            $brandName = $this->nullableString($data['brand_name'] ?? null) ?? trim((string) ($data['company_name'] ?? $data['organization_name']));
            $tagline = $this->nullableString($data['tagline'] ?? null) ?? ('Panel administrativo de '.$brandName);
            $contactEmail = Str::lower(trim((string) $data['contact_email']));
            $supportEmail = $this->nullableString($data['support_email'] ?? null) ?? $contactEmail;
            $supportPhone = $this->nullableString($data['support_phone'] ?? null) ?? $this->nullableString($data['phone'] ?? null) ?? $this->nullableString($data['mobile'] ?? null);

            CommerceSetting::query()->updateOrCreate(
                ['organization_id' => $organization->id],
                [
                    'brand_name' => $brandName,
                    'company_name' => trim((string) ($data['company_name'] ?? $data['organization_name'])),
                    'tagline' => $tagline,
                    'tax_id' => $this->nullableString($data['tax_id'] ?? null),
                    'address' => $this->nullableString($data['address'] ?? null),
                    'phone' => $this->nullableString($data['phone'] ?? null),
                    'mobile' => $this->nullableString($data['mobile'] ?? null),
                    'support_phone' => $supportPhone,
                    'email' => $contactEmail,
                    'support_email' => $supportEmail,
                ],
            );

            $this->seedDefaultSecurityAuthBranding($organization->id, $brandName, $tagline);

            return $organization->fresh();
        });
    }

    public function updatePrimaryBranch(Organization $organization, array $data): SecurityBranch
    {
        return DB::transaction(function () use ($organization, $data): SecurityBranch {
            $branch = $organization->branches()->where('is_default', true)->first();

            if (! $branch) {
                $organization->branches()->update(['is_default' => false]);
                $branch = new SecurityBranch(['organization_id' => $organization->id, 'is_default' => true]);
            }

            $branch->fill([
                'organization_id' => $organization->id,
                'code' => Str::upper(trim((string) $data['branch_code'])),
                'name' => trim((string) $data['branch_name']),
                'city' => $this->nullableString($data['branch_city'] ?? null),
                'address' => $this->nullableString($data['branch_address'] ?? null),
                'phone' => $this->nullableString($data['branch_phone'] ?? null),
                'is_active' => (bool) ($data['branch_is_active'] ?? false),
                'is_default' => true,
            ]);
            $branch->save();

            return $branch->fresh();
        });
    }

    public function recoverPrimaryBranch(Organization $organization): SecurityBranch
    {
        return DB::transaction(function () use ($organization): SecurityBranch {
            $organization->branches()->update(['is_default' => false]);

            $code = 'MAIN';
            $suffix = 1;
            while (SecurityBranch::query()->where('organization_id', $organization->id)->where('code', $code)->exists()) {
                $suffix++;
                $code = 'MAIN'.$suffix;
            }

            $branch = SecurityBranch::query()->create([
                'organization_id' => $organization->id,
                'code' => $code,
                'name' => 'Sucursal Principal',
                'city' => null,
                'address' => null,
                'phone' => null,
                'is_active' => true,
                'is_default' => true,
            ]);

            return $branch->fresh();
        });
    }

    public function updateInitialAdmin(Organization $organization, array $data): User
    {
        return DB::transaction(function () use ($organization, $data): User {
            $branch = $organization->branches()->where('is_default', true)->first();
            $superAdminRole = SecurityRole::query()->where('code', 'super_admin')->first();

            if (! $superAdminRole) {
                throw new RuntimeException('No existe el rol super_admin para asignar al usuario administrador.');
            }

            $admin = $organization->users()
                ->whereHas('roles', fn ($query) => $query->where('code', 'super_admin'))
                ->orderBy('id')
                ->first();

            if (! $admin) {
                $admin = $organization->users()->orderBy('id')->first();
            }

            if (! $admin) {
                $admin = new User([
                    'organization_id' => $organization->id,
                    'domain' => 'internal',
                    'role' => 'super_admin',
                ]);
            }

            $admin->fill([
                'organization_id' => $organization->id,
                'branch_id' => $branch?->id,
                'name' => trim((string) $data['admin_name']),
                'email' => Str::lower(trim((string) $data['admin_email'])),
                'phone' => $this->nullableString($data['admin_phone'] ?? null),
                'is_active' => (bool) ($data['admin_is_active'] ?? false),
                'domain' => 'internal',
                'role' => 'super_admin',
            ]);

            if ($this->nullableString($data['admin_password'] ?? null) !== null) {
                $admin->password = (string) $data['admin_password'];
            }

            $admin->save();

            $admin->roles()->syncWithoutDetaching([
                $superAdminRole->id => [
                    'scope' => 'all',
                    'is_active' => true,
                    'context' => json_encode(['source' => 'organization_admin_maintenance']),
                ],
            ]);

            return $admin->fresh();
        });
    }

    public function recoverInitialAdmin(Organization $organization): array
    {
        return DB::transaction(function () use ($organization): array {
            $branch = $organization->branches()->where('is_default', true)->first();
            $superAdminRole = SecurityRole::query()->where('code', 'super_admin')->first();

            if (! $branch) {
                throw new RuntimeException('No existe una sucursal principal para reconstruir el administrador inicial.');
            }

            if (! $superAdminRole) {
                throw new RuntimeException('No existe el rol super_admin para reconstruir el administrador inicial.');
            }

            $generatedPassword = Str::password(14);
            $emailPrefix = Str::slug($organization->code !== '' ? $organization->code : $organization->name, '.');
            $emailPrefix = $emailPrefix !== '' ? $emailPrefix : 'tenant';
            $email = $emailPrefix.'.admin@tenant.local';
            $suffix = 1;

            while (User::query()->where('organization_id', $organization->id)->where('email', $email)->exists()) {
                $suffix++;
                $email = $emailPrefix.'.admin'.$suffix.'@tenant.local';
            }

            $admin = User::query()->create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => 'Administrador Inicial',
                'email' => $email,
                'phone' => null,
                'role' => 'super_admin',
                'domain' => 'internal',
                'password' => $generatedPassword,
                'is_active' => true,
            ]);

            $admin->roles()->syncWithoutDetaching([
                $superAdminRole->id => [
                    'scope' => 'all',
                    'is_active' => true,
                    'context' => json_encode(['source' => 'organization_admin_recovery']),
                ],
            ]);

            return [
                'admin' => $admin->fresh(),
                'password' => $generatedPassword,
            ];
        });
    }

    public function suspendOrganization(Organization $organization): Organization
    {
        $settings = is_array($organization->settings_json) ? $organization->settings_json : [];
        $settings['suspended_at'] = now()->toDateTimeString();

        $organization->forceFill([
            'status' => 'suspended',
            'settings_json' => $settings,
        ])->save();

        return $organization->fresh();
    }

    public function reactivateOrganization(Organization $organization): Organization
    {
        $settings = is_array($organization->settings_json) ? $organization->settings_json : [];
        $settings['reactivated_at'] = now()->toDateTimeString();

        $organization->forceFill([
            'status' => 'active',
            'settings_json' => $settings,
        ])->save();

        return $organization->fresh();
    }

    public function productionReadinessChecks(Organization $organization): array
    {
        $organization->loadMissing(['branches', 'users']);

        $activeBranches = $organization->branches->where('is_active', true);
        $defaultBranch = $organization->branches->firstWhere('is_default', true);
        $activeAdmins = $organization->users->filter(function (User $user): bool {
            return (bool) $user->is_active && $user->roles()->where('code', 'super_admin')->wherePivot('is_active', true)->exists();
        });
        $commerce = CommerceSetting::query()->where('organization_id', $organization->id)->first();
        $billing = BillingSetting::query()->where('organization_id', $organization->id)->first();
        $accounting = AccountingSetting::query()->where('organization_id', $organization->id)->first();

        return [
            [
                'ok' => trim((string) $organization->name) !== '' && trim((string) $organization->code) !== '' && trim((string) $organization->slug) !== '',
                'label' => 'Identidad de organización',
                'message' => 'Debe existir nombre, código y slug de organización.',
            ],
            [
                'ok' => $organization->status === 'active',
                'label' => 'Estado organizacional',
                'message' => 'La organización debe estar activa para pasar a producción.',
            ],
            [
                'ok' => $activeBranches->isNotEmpty(),
                'label' => 'Sucursales activas',
                'message' => 'Debe existir al menos una sucursal activa.',
            ],
            [
                'ok' => $defaultBranch !== null && (bool) $defaultBranch->is_active,
                'label' => 'Sucursal principal',
                'message' => 'Debe existir una sucursal principal activa.',
            ],
            [
                'ok' => $activeAdmins->isNotEmpty(),
                'label' => 'Administrador inicial',
                'message' => 'Debe existir al menos un usuario admin activo.',
            ],
            [
                'ok' => $commerce !== null && trim((string) $commerce->company_name) !== '' && trim((string) $commerce->email) !== '',
                'label' => 'Configuración comercial',
                'message' => 'Debe existir commerce settings con nombre comercial y email.',
            ],
            [
                'ok' => $billing !== null && trim((string) $billing->provider) !== '' && trim((string) $billing->country) !== '',
                'label' => 'Configuración de facturación',
                'message' => 'Debe existir billing settings base para la organización.',
            ],
            [
                'ok' => $accounting !== null && trim((string) $accounting->default_currency) !== '' && (int) $accounting->fiscal_year >= 2000,
                'label' => 'Configuración contable',
                'message' => 'Debe existir accounting settings con año fiscal y moneda.',
            ],
            [
                'ok' => trim((string) $organization->tax_id) !== '',
                'label' => 'Identificación fiscal',
                'message' => 'Debe registrarse RUC o Tax ID antes de producción.',
            ],
        ];
    }

    public function canActivateProduction(Organization $organization): bool
    {
        return collect($this->productionReadinessChecks($organization))->every(fn (array $check): bool => (bool) $check['ok']);
    }

    public function activateProduction(Organization $organization): Organization
    {
        if (! $this->canActivateProduction($organization)) {
            throw new RuntimeException('La organización no cumple las validaciones mínimas para pasar a producción.');
        }

        $settings = is_array($organization->settings_json) ? $organization->settings_json : [];
        $settings['production_activated_at'] = now()->toDateTimeString();

        $organization->forceFill([
            'environment' => 'production',
            'status' => 'active',
            'settings_json' => $settings,
        ])->save();

        return $organization->fresh();
    }

    private function seedDefaultAdminPalette(int $organizationId): void
    {
        if (! class_exists(AdminThemeSetting::class)) {
            return;
        }

        $defaults = config('admintheme.defaults', []);

        if ($defaults === []) {
            return;
        }

        AdminThemeSetting::query()->updateOrCreate(
            ['organization_id' => $organizationId],
            array_merge(['organization_id' => $organizationId], $defaults)
        );
    }

    private function seedDefaultSecurityAuthBranding(int $organizationId, string $brandName, string $tagline): void
    {
        if (! class_exists(SecurityAuthSetting::class)) {
            return;
        }

        $defaults = config('security.auth', []);

        if ($defaults === []) {
            return;
        }

        SecurityAuthSetting::query()->updateOrCreate(
            ['organization_id' => $organizationId],
            [
                'organization_id' => $organizationId,
                'login_headline' => $defaults['login_headline'] ?? ('Ingreso seguro a '.$brandName),
                'login_slogan' => $tagline,
            ]
        );
    }

    private function resolveBranchCode(string $branchName): string
    {
        $sanitized = Str::upper(Str::slug($branchName, ''));

        return $sanitized !== '' ? Str::limit($sanitized, 12, '') : 'MAIN';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
