<?php

namespace Modules\Security\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Security\Models\SecurityBranch;
use Modules\Security\Models\SecurityModule;
use Modules\Security\Models\SecurityPermission;
use Modules\Security\Models\SecurityRole;

class SecurityDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBranches();
        $moduleMap = $this->seedModules();
        $roleMap = $this->seedRoles();
        $permissionMap = $this->seedPermissions($moduleMap);

        $this->seedRoleModuleAccess($roleMap, $moduleMap);
        $this->seedRolePermissions($roleMap, $permissionMap);
        $this->assignExistingUsersByLegacyRole($roleMap);
    }

    private function seedBranches(): void
    {
        SecurityBranch::query()->updateOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Sucursal principal',
                'city' => null,
                'address' => null,
                'phone' => null,
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }

    private function seedModules(): array
    {
        $records = [
            ['code' => 'dashboard', 'name' => 'Dashboard ejecutivo', 'status' => 'implemented', 'sort_order' => 10],
            ['code' => 'sales', 'name' => 'Ventas / pedidos', 'status' => 'implemented', 'sort_order' => 20],
            ['code' => 'pos', 'name' => 'Punto de venta', 'status' => 'implemented', 'sort_order' => 30],
            ['code' => 'customers', 'name' => 'Clientes', 'status' => 'implemented', 'sort_order' => 40],
            ['code' => 'catalog', 'name' => 'Catalogo / productos', 'status' => 'implemented', 'sort_order' => 50],
            ['code' => 'billing', 'name' => 'Facturacion electronica', 'status' => 'implemented', 'sort_order' => 60],
            ['code' => 'accounting', 'name' => 'Contabilidad', 'status' => 'partial', 'sort_order' => 70],
            ['code' => 'commerce', 'name' => 'Configuracion de comercio', 'status' => 'implemented', 'sort_order' => 80],
            ['code' => 'admin_theme', 'name' => 'Tema / paleta admin', 'status' => 'implemented', 'sort_order' => 90],
            ['code' => 'security', 'name' => 'Seguridad / autenticacion', 'status' => 'partial', 'sort_order' => 100],
            ['code' => 'orders_front', 'name' => 'Pedidos ecommerce', 'status' => 'implemented', 'sort_order' => 110],
            ['code' => 'imports', 'name' => 'Importaciones', 'status' => 'en_construccion', 'sort_order' => 120],
            ['code' => 'finance', 'name' => 'Finanzas', 'status' => 'en_construccion', 'sort_order' => 130],
            ['code' => 'crm', 'name' => 'CRM', 'status' => 'en_construccion', 'sort_order' => 140],
            ['code' => 'reports', 'name' => 'Reportes', 'status' => 'en_construccion', 'sort_order' => 150],
            ['code' => 'inventory', 'name' => 'Inventarios', 'status' => 'en_construccion', 'sort_order' => 160],
            ['code' => 'warranties', 'name' => 'Garantias', 'status' => 'en_construccion', 'sort_order' => 170],
            ['code' => 'payroll', 'name' => 'Planillas', 'status' => 'en_construccion', 'sort_order' => 180],
        ];

        $map = [];

        foreach ($records as $record) {
            $module = SecurityModule::query()->updateOrCreate(
                ['code' => $record['code']],
                [
                    'name' => $record['name'],
                    'description' => $record['name'],
                    'status' => $record['status'],
                    'navigation_visible' => true,
                    'sort_order' => $record['sort_order'],
                ]
            );

            $map[$record['code']] = $module;
        }

        return $map;
    }

    private function seedRoles(): array
    {
        $records = [
            ['code' => 'super_admin', 'name' => 'Super administrador'],
            ['code' => 'security_admin', 'name' => 'Administrador de seguridad'],
            ['code' => 'general_manager', 'name' => 'Gerencia general'],
            ['code' => 'sales_manager', 'name' => 'Jefe comercial'],
            ['code' => 'sales_cashier', 'name' => 'Cajero POS'],
            ['code' => 'billing_manager', 'name' => 'Responsable de facturacion'],
            ['code' => 'catalog_manager', 'name' => 'Responsable de catalogo'],
            ['code' => 'accounting_manager', 'name' => 'Responsable contable'],
            ['code' => 'warehouse_manager', 'name' => 'Responsable de inventario'],
            ['code' => 'crm_manager', 'name' => 'Responsable CRM'],
            ['code' => 'finance_manager', 'name' => 'Responsable de finanzas'],
            ['code' => 'hr_manager', 'name' => 'Responsable de planillas'],
            ['code' => 'support_agent', 'name' => 'Soporte / atencion'],
            ['code' => 'customer', 'name' => 'Cliente ecommerce'],
        ];

        $map = [];

        foreach ($records as $record) {
            $role = SecurityRole::query()->updateOrCreate(
                ['code' => $record['code']],
                [
                    'name' => $record['name'],
                    'description' => $record['name'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );

            $map[$record['code']] = $role;
        }

        return $map;
    }

    private function seedPermissions(array $moduleMap): array
    {
        $records = [
            ['module' => 'dashboard', 'resource' => 'overview', 'action' => 'view', 'code' => 'dashboard.overview.view'],
            ['module' => 'sales', 'resource' => 'orders', 'action' => 'view', 'code' => 'sales.orders.view'],
            ['module' => 'sales', 'resource' => 'orders', 'action' => 'create', 'code' => 'sales.orders.create'],
            ['module' => 'sales', 'resource' => 'orders', 'action' => 'update', 'code' => 'sales.orders.update'],
            ['module' => 'sales', 'resource' => 'orders', 'action' => 'delete', 'code' => 'sales.orders.delete'],
            ['module' => 'sales', 'resource' => 'orders', 'action' => 'approve', 'code' => 'sales.orders.approve'],
            ['module' => 'sales', 'resource' => 'orders', 'action' => 'export', 'code' => 'sales.orders.export'],
            ['module' => 'pos', 'resource' => 'sales', 'action' => 'view', 'code' => 'pos.sales.view'],
            ['module' => 'pos', 'resource' => 'sales', 'action' => 'create', 'code' => 'pos.sales.create'],
            ['module' => 'pos', 'resource' => 'sales', 'action' => 'update', 'code' => 'pos.sales.update'],
            ['module' => 'pos', 'resource' => 'sales', 'action' => 'approve', 'code' => 'pos.sales.approve'],
            ['module' => 'customers', 'resource' => 'records', 'action' => 'view', 'code' => 'customers.records.view'],
            ['module' => 'customers', 'resource' => 'records', 'action' => 'create', 'code' => 'customers.records.create'],
            ['module' => 'customers', 'resource' => 'records', 'action' => 'update', 'code' => 'customers.records.update'],
            ['module' => 'customers', 'resource' => 'records', 'action' => 'delete', 'code' => 'customers.records.delete'],
            ['module' => 'customers', 'resource' => 'records', 'action' => 'export', 'code' => 'customers.records.export'],
            ['module' => 'catalog', 'resource' => 'products', 'action' => 'view', 'code' => 'catalog.products.view'],
            ['module' => 'catalog', 'resource' => 'products', 'action' => 'create', 'code' => 'catalog.products.create'],
            ['module' => 'catalog', 'resource' => 'products', 'action' => 'update', 'code' => 'catalog.products.update'],
            ['module' => 'catalog', 'resource' => 'products', 'action' => 'delete', 'code' => 'catalog.products.delete'],
            ['module' => 'catalog', 'resource' => 'products', 'action' => 'export', 'code' => 'catalog.products.export'],
            ['module' => 'catalog', 'resource' => 'categories', 'action' => 'view', 'code' => 'catalog.categories.view'],
            ['module' => 'catalog', 'resource' => 'categories', 'action' => 'create', 'code' => 'catalog.categories.create'],
            ['module' => 'catalog', 'resource' => 'categories', 'action' => 'update', 'code' => 'catalog.categories.update'],
            ['module' => 'catalog', 'resource' => 'unit_measures', 'action' => 'view', 'code' => 'catalog.unit_measures.view'],
            ['module' => 'catalog', 'resource' => 'unit_measures', 'action' => 'create', 'code' => 'catalog.unit_measures.create'],
            ['module' => 'catalog', 'resource' => 'unit_measures', 'action' => 'update', 'code' => 'catalog.unit_measures.update'],
            ['module' => 'billing', 'resource' => 'documents', 'action' => 'view', 'code' => 'billing.documents.view'],
            ['module' => 'billing', 'resource' => 'documents', 'action' => 'create', 'code' => 'billing.documents.create'],
            ['module' => 'billing', 'resource' => 'documents', 'action' => 'update', 'code' => 'billing.documents.update'],
            ['module' => 'billing', 'resource' => 'documents', 'action' => 'approve', 'code' => 'billing.documents.approve'],
            ['module' => 'billing', 'resource' => 'documents', 'action' => 'export', 'code' => 'billing.documents.export'],
            ['module' => 'billing', 'resource' => 'documents', 'action' => 'reprocess', 'code' => 'billing.documents.reprocess'],
            ['module' => 'billing', 'resource' => 'documents', 'action' => 'audit', 'code' => 'billing.documents.audit'],
            ['module' => 'accounting', 'resource' => 'entries', 'action' => 'view', 'code' => 'accounting.entries.view'],
            ['module' => 'accounting', 'resource' => 'entries', 'action' => 'create', 'code' => 'accounting.entries.create'],
            ['module' => 'accounting', 'resource' => 'entries', 'action' => 'update', 'code' => 'accounting.entries.update'],
            ['module' => 'accounting', 'resource' => 'entries', 'action' => 'approve', 'code' => 'accounting.entries.approve'],
            ['module' => 'accounting', 'resource' => 'entries', 'action' => 'export', 'code' => 'accounting.entries.export'],
            ['module' => 'accounting', 'resource' => 'settings', 'action' => 'configure', 'code' => 'accounting.settings.configure'],
            ['module' => 'accounting', 'resource' => 'audit', 'action' => 'view', 'code' => 'accounting.audit.view'],
            ['module' => 'commerce', 'resource' => 'settings', 'action' => 'view', 'code' => 'commerce.settings.view'],
            ['module' => 'commerce', 'resource' => 'settings', 'action' => 'update', 'code' => 'commerce.settings.update'],
            ['module' => 'commerce', 'resource' => 'settings', 'action' => 'configure', 'code' => 'commerce.settings.configure'],
            ['module' => 'admin_theme', 'resource' => 'palette', 'action' => 'view', 'code' => 'admin_theme.palette.view'],
            ['module' => 'admin_theme', 'resource' => 'palette', 'action' => 'update', 'code' => 'admin_theme.palette.update'],
            ['module' => 'admin_theme', 'resource' => 'palette', 'action' => 'configure', 'code' => 'admin_theme.palette.configure'],
            ['module' => 'security', 'resource' => 'roles', 'action' => 'view', 'code' => 'security.roles.view'],
            ['module' => 'security', 'resource' => 'roles', 'action' => 'create', 'code' => 'security.roles.create'],
            ['module' => 'security', 'resource' => 'roles', 'action' => 'update', 'code' => 'security.roles.update'],
            ['module' => 'security', 'resource' => 'permissions', 'action' => 'view', 'code' => 'security.permissions.view'],
            ['module' => 'security', 'resource' => 'permissions', 'action' => 'assign', 'code' => 'security.permissions.assign'],
            ['module' => 'security', 'resource' => 'users', 'action' => 'view', 'code' => 'security.users.view'],
            ['module' => 'security', 'resource' => 'users', 'action' => 'assign', 'code' => 'security.users.assign'],
            ['module' => 'security', 'resource' => 'branches', 'action' => 'view', 'code' => 'security.branches.view'],
            ['module' => 'security', 'resource' => 'branches', 'action' => 'update', 'code' => 'security.branches.update'],
            ['module' => 'security', 'resource' => 'auth', 'action' => 'view', 'code' => 'security.auth.view'],
            ['module' => 'security', 'resource' => 'auth', 'action' => 'configure', 'code' => 'security.auth.configure'],
            ['module' => 'security', 'resource' => 'audit', 'action' => 'view', 'code' => 'security.audit.view'],
            ['module' => 'orders_front', 'resource' => 'orders', 'action' => 'view', 'code' => 'orders_front.orders.view'],
            ['module' => 'orders_front', 'resource' => 'orders', 'action' => 'create', 'code' => 'orders_front.orders.create'],
            ['module' => 'imports', 'resource' => 'module', 'action' => 'view', 'code' => 'imports.module.view'],
            ['module' => 'finance', 'resource' => 'module', 'action' => 'view', 'code' => 'finance.module.view'],
            ['module' => 'crm', 'resource' => 'module', 'action' => 'view', 'code' => 'crm.module.view'],
            ['module' => 'reports', 'resource' => 'module', 'action' => 'view', 'code' => 'reports.module.view'],
            ['module' => 'inventory', 'resource' => 'module', 'action' => 'view', 'code' => 'inventory.module.view'],
            ['module' => 'inventory', 'resource' => 'adjustments', 'action' => 'update', 'code' => 'inventory.adjustments.update'],
            ['module' => 'inventory', 'resource' => 'transfers', 'action' => 'view', 'code' => 'inventory.transfers.view'],
            ['module' => 'inventory', 'resource' => 'transfers', 'action' => 'create', 'code' => 'inventory.transfers.create'],
            ['module' => 'warranties', 'resource' => 'module', 'action' => 'view', 'code' => 'warranties.module.view'],
            ['module' => 'payroll', 'resource' => 'module', 'action' => 'view', 'code' => 'payroll.module.view'],
        ];

        $map = [];

        foreach ($records as $record) {
            $permission = SecurityPermission::query()->updateOrCreate(
                ['code' => $record['code']],
                [
                    'module_id' => $moduleMap[$record['module']]->id ?? null,
                    'resource' => $record['resource'],
                    'action' => $record['action'],
                    'description' => $record['code'],
                ]
            );

            $map[$record['code']] = $permission;
        }

        return $map;
    }

    private function seedRoleModuleAccess(array $roleMap, array $moduleMap): void
    {
        $matrix = [
            'super_admin' => ['dashboard' => 'full', 'sales' => 'full', 'pos' => 'full', 'customers' => 'full', 'catalog' => 'full', 'billing' => 'full', 'accounting' => 'full', 'commerce' => 'full', 'admin_theme' => 'full', 'security' => 'full', 'orders_front' => 'full', 'imports' => 'placeholder', 'finance' => 'placeholder', 'crm' => 'placeholder', 'reports' => 'placeholder', 'inventory' => 'placeholder', 'warranties' => 'placeholder', 'payroll' => 'placeholder'],
            'security_admin' => ['dashboard' => 'readonly', 'customers' => 'readonly', 'billing' => 'readonly', 'accounting' => 'readonly', 'admin_theme' => 'readonly', 'security' => 'full', 'reports' => 'readonly', 'imports' => 'placeholder', 'finance' => 'placeholder', 'crm' => 'placeholder', 'inventory' => 'placeholder', 'warranties' => 'placeholder', 'payroll' => 'placeholder'],
            'general_manager' => ['dashboard' => 'readonly', 'sales' => 'readonly', 'pos' => 'readonly', 'customers' => 'readonly', 'catalog' => 'readonly', 'billing' => 'readonly', 'accounting' => 'readonly', 'commerce' => 'readonly', 'security' => 'readonly', 'orders_front' => 'readonly', 'imports' => 'placeholder', 'finance' => 'placeholder', 'crm' => 'placeholder', 'reports' => 'placeholder', 'inventory' => 'placeholder', 'warranties' => 'placeholder', 'payroll' => 'placeholder'],
            'sales_manager' => ['dashboard' => 'readonly', 'sales' => 'full', 'pos' => 'full', 'customers' => 'full', 'catalog' => 'readonly', 'billing' => 'readonly', 'crm' => 'readonly', 'reports' => 'readonly', 'orders_front' => 'readonly'],
            'sales_cashier' => ['dashboard' => 'readonly', 'sales' => 'limited', 'pos' => 'full', 'customers' => 'limited', 'catalog' => 'readonly', 'billing' => 'limited'],
            'billing_manager' => ['dashboard' => 'readonly', 'sales' => 'readonly', 'pos' => 'readonly', 'customers' => 'readonly', 'catalog' => 'readonly', 'billing' => 'full', 'accounting' => 'readonly', 'orders_front' => 'readonly', 'reports' => 'readonly'],
            'catalog_manager' => ['dashboard' => 'readonly', 'sales' => 'readonly', 'customers' => 'readonly', 'catalog' => 'full', 'billing' => 'readonly', 'reports' => 'readonly', 'inventory' => 'placeholder'],
            'accounting_manager' => ['dashboard' => 'readonly', 'sales' => 'readonly', 'customers' => 'readonly', 'catalog' => 'readonly', 'billing' => 'readonly', 'accounting' => 'full', 'finance' => 'placeholder', 'reports' => 'readonly', 'inventory' => 'placeholder'],
            'warehouse_manager' => ['dashboard' => 'readonly', 'sales' => 'readonly', 'pos' => 'readonly', 'catalog' => 'readonly', 'reports' => 'readonly', 'imports' => 'placeholder', 'inventory' => 'placeholder', 'warranties' => 'placeholder'],
            'crm_manager' => ['dashboard' => 'readonly', 'sales' => 'readonly', 'customers' => 'full', 'catalog' => 'readonly', 'orders_front' => 'readonly', 'crm' => 'placeholder', 'reports' => 'readonly'],
            'finance_manager' => ['dashboard' => 'readonly', 'sales' => 'readonly', 'customers' => 'readonly', 'catalog' => 'readonly', 'billing' => 'readonly', 'accounting' => 'readonly', 'finance' => 'placeholder', 'reports' => 'readonly'],
            'hr_manager' => ['dashboard' => 'readonly', 'customers' => 'readonly', 'accounting' => 'readonly', 'reports' => 'readonly', 'payroll' => 'placeholder'],
            'support_agent' => ['dashboard' => 'readonly', 'sales' => 'readonly', 'customers' => 'readonly', 'catalog' => 'readonly', 'billing' => 'readonly', 'orders_front' => 'readonly', 'warranties' => 'placeholder'],
            'customer' => ['catalog' => 'readonly', 'orders_front' => 'limited'],
        ];

        foreach ($matrix as $roleCode => $modules) {
            $payload = [];

            foreach ($modules as $moduleCode => $level) {
                $payload[$moduleMap[$moduleCode]->id] = [
                    'access_level' => $level,
                    'navigation_visible' => $level !== 'none',
                ];
            }

            $roleMap[$roleCode]->modules()->sync($payload);
        }
    }

    private function seedRolePermissions(array $roleMap, array $permissionMap): void
    {
        $allPermissions = array_keys($permissionMap);

        $matrix = [
            'super_admin' => $allPermissions,
            'security_admin' => ['dashboard.overview.view', 'customers.records.view', 'billing.documents.view', 'billing.documents.audit', 'accounting.entries.view', 'accounting.audit.view', 'admin_theme.palette.view', 'admin_theme.palette.update', 'admin_theme.palette.configure', 'security.roles.view', 'security.roles.create', 'security.roles.update', 'security.permissions.view', 'security.permissions.assign', 'security.users.view', 'security.users.assign', 'security.branches.view', 'security.branches.update', 'security.auth.view', 'security.auth.configure', 'security.audit.view', 'reports.module.view'],
            'general_manager' => ['dashboard.overview.view', 'sales.orders.view', 'sales.orders.approve', 'sales.orders.export', 'pos.sales.view', 'customers.records.view', 'catalog.products.view', 'billing.documents.view', 'billing.documents.approve', 'billing.documents.export', 'billing.documents.audit', 'accounting.entries.view', 'accounting.entries.export', 'commerce.settings.view', 'security.auth.view', 'security.roles.view', 'security.permissions.view', 'security.users.view', 'security.branches.view', 'orders_front.orders.view', 'imports.module.view', 'finance.module.view', 'crm.module.view', 'reports.module.view', 'inventory.module.view', 'inventory.transfers.view', 'warranties.module.view', 'payroll.module.view'],
            'sales_manager' => ['dashboard.overview.view', 'sales.orders.view', 'sales.orders.create', 'sales.orders.update', 'sales.orders.approve', 'sales.orders.export', 'pos.sales.view', 'pos.sales.create', 'pos.sales.update', 'pos.sales.approve', 'customers.records.view', 'customers.records.create', 'customers.records.update', 'catalog.products.view', 'billing.documents.view', 'billing.documents.export', 'crm.module.view', 'reports.module.view'],
            'sales_cashier' => ['dashboard.overview.view', 'sales.orders.view', 'sales.orders.create', 'pos.sales.view', 'pos.sales.create', 'pos.sales.update', 'customers.records.view', 'customers.records.create', 'customers.records.update', 'catalog.products.view', 'billing.documents.view', 'billing.documents.create'],
            'billing_manager' => ['dashboard.overview.view', 'sales.orders.view', 'pos.sales.view', 'customers.records.view', 'catalog.products.view', 'billing.documents.view', 'billing.documents.create', 'billing.documents.update', 'billing.documents.approve', 'billing.documents.export', 'billing.documents.reprocess', 'billing.documents.audit', 'accounting.entries.view', 'orders_front.orders.view', 'reports.module.view'],
            'catalog_manager' => ['dashboard.overview.view', 'sales.orders.view', 'customers.records.view', 'catalog.products.view', 'catalog.products.create', 'catalog.products.update', 'catalog.products.delete', 'catalog.products.export', 'catalog.categories.view', 'catalog.categories.create', 'catalog.categories.update', 'catalog.unit_measures.view', 'catalog.unit_measures.create', 'catalog.unit_measures.update', 'billing.documents.view', 'reports.module.view', 'inventory.module.view'],
            'accounting_manager' => ['dashboard.overview.view', 'sales.orders.view', 'customers.records.view', 'catalog.products.view', 'billing.documents.view', 'billing.documents.audit', 'billing.documents.export', 'accounting.entries.view', 'accounting.entries.create', 'accounting.entries.update', 'accounting.entries.approve', 'accounting.entries.export', 'accounting.settings.configure', 'accounting.audit.view', 'finance.module.view', 'reports.module.view', 'inventory.module.view'],
            'warehouse_manager' => ['dashboard.overview.view', 'sales.orders.view', 'pos.sales.view', 'catalog.products.view', 'catalog.products.update', 'reports.module.view', 'imports.module.view', 'inventory.module.view', 'inventory.adjustments.update', 'inventory.transfers.view', 'inventory.transfers.create', 'warranties.module.view'],
            'crm_manager' => ['dashboard.overview.view', 'sales.orders.view', 'customers.records.view', 'customers.records.create', 'customers.records.update', 'customers.records.export', 'catalog.products.view', 'orders_front.orders.view', 'crm.module.view', 'reports.module.view'],
            'finance_manager' => ['dashboard.overview.view', 'sales.orders.view', 'customers.records.view', 'catalog.products.view', 'billing.documents.view', 'billing.documents.audit', 'billing.documents.export', 'accounting.entries.view', 'accounting.entries.export', 'finance.module.view', 'reports.module.view'],
            'hr_manager' => ['dashboard.overview.view', 'customers.records.view', 'accounting.entries.view', 'reports.module.view', 'payroll.module.view'],
            'support_agent' => ['dashboard.overview.view', 'sales.orders.view', 'customers.records.view', 'customers.records.update', 'catalog.products.view', 'billing.documents.view', 'billing.documents.audit', 'orders_front.orders.view', 'warranties.module.view'],
            'customer' => ['catalog.products.view', 'orders_front.orders.view', 'orders_front.orders.create'],
        ];

        foreach ($matrix as $roleCode => $permissionCodes) {
            $roleMap[$roleCode]->permissions()->sync(
                collect($permissionCodes)
                    ->filter(fn (string $code) => isset($permissionMap[$code]))
                    ->map(fn (string $code) => $permissionMap[$code]->id)
                    ->values()
                    ->all()
            );
        }
    }

    private function assignExistingUsersByLegacyRole(array $roleMap): void
    {
        User::query()
            ->whereNotNull('role')
            ->whereIn('role', array_keys($roleMap))
            ->each(function (User $user) use ($roleMap): void {
                $role = $roleMap[$user->role] ?? null;

                if (! $role) {
                    return;
                }

                $role->users()->syncWithoutDetaching([
                    $user->id => [
                        'scope' => 'all',
                        'is_active' => true,
                        'context' => null,
                    ],
                ]);
            });
    }
}


