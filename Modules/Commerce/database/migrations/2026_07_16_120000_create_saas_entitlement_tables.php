<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('is_technical_core')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('saas_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->string('kind', 20)->default('plan');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['kind', 'is_active']);
        });

        Schema::create('saas_plan_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('saas_plans')->cascadeOnDelete();
            $table->foreignId('capability_id')->constrained('saas_capabilities')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['plan_id', 'capability_id']);
        });

        Schema::create('organization_plan_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('saas_plans')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'starts_at', 'ends_at'], 'organization_plan_subscriptions_active_idx');
        });

        Schema::create('organization_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('capability_id')->constrained('saas_capabilities')->restrictOnDelete();
            $table->string('state', 20);
            $table->string('source', 40)->default('manual');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'capability_id']);
            $table->index(['organization_id', 'state', 'starts_at', 'ends_at'], 'organization_entitlements_active_idx');
        });

        $this->seedInitialCatalog();
        $this->assignLegacyPlanToExistingOrganizations();
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_entitlements');
        Schema::dropIfExists('organization_plan_subscriptions');
        Schema::dropIfExists('saas_plan_capabilities');
        Schema::dropIfExists('saas_plans');
        Schema::dropIfExists('saas_capabilities');
    }

    private function seedInitialCatalog(): void
    {
        $now = now();
        $capabilities = [
            ['code' => 'catalog.products', 'name' => 'Catálogo de productos', 'description' => 'Mantenimiento y consulta del catálogo base.', 'is_technical_core' => true, 'sort_order' => 10],
            ['code' => 'inventory.core.stock', 'name' => 'Stock operativo base', 'description' => 'Registro técnico mínimo de existencias.', 'is_technical_core' => true, 'sort_order' => 20],
            ['code' => 'inventory.core.movements', 'name' => 'Movimientos operativos base', 'description' => 'Trazabilidad técnica mínima de entradas y salidas.', 'is_technical_core' => true, 'sort_order' => 30],
            ['code' => 'sales.orders', 'name' => 'Ventas y pedidos', 'description' => 'Gestión comercial de pedidos.', 'is_technical_core' => false, 'sort_order' => 40],
            ['code' => 'sales.pos', 'name' => 'Punto de venta', 'description' => 'Operación de caja POS.', 'is_technical_core' => false, 'sort_order' => 50],
            ['code' => 'sales.customers', 'name' => 'Clientes', 'description' => 'Gestión de clientes comerciales.', 'is_technical_core' => false, 'sort_order' => 60],
            ['code' => 'sales.ecommerce', 'name' => 'Pedidos e-commerce', 'description' => 'Canal de pedidos en línea.', 'is_technical_core' => false, 'sort_order' => 70],
            ['code' => 'billing.electronic', 'name' => 'Facturación electrónica', 'description' => 'Emisión y consulta de comprobantes electrónicos.', 'is_technical_core' => false, 'sort_order' => 80],
            ['code' => 'inventory.advanced', 'name' => 'Inventario avanzado', 'description' => 'Almacenes, documentos, kardex avanzado y transferencias.', 'is_technical_core' => false, 'sort_order' => 90],
            ['code' => 'accounting.general_ledger', 'name' => 'Contabilidad', 'description' => 'Plan de cuentas, asientos, períodos y reportes contables.', 'is_technical_core' => false, 'sort_order' => 100],
        ];

        foreach ($capabilities as $capability) {
            DB::table('saas_capabilities')->updateOrInsert(
                ['code' => $capability['code']],
                array_merge($capability, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }

        $plans = [
            ['code' => 'basic', 'name' => 'Básico', 'kind' => 'plan', 'description' => 'Plan comercial base.', 'capabilities' => ['sales.orders', 'sales.pos', 'sales.customers', 'sales.ecommerce', 'billing.electronic']],
            ['code' => 'enterprise', 'name' => 'Empresarial', 'kind' => 'plan', 'description' => 'Plan con inventario y contabilidad avanzada.', 'capabilities' => ['sales.orders', 'sales.pos', 'sales.customers', 'sales.ecommerce', 'billing.electronic', 'inventory.advanced', 'accounting.general_ledger']],
            ['code' => 'inventory_advanced', 'name' => 'Inventario avanzado', 'kind' => 'addon', 'description' => 'Addon de inventario avanzado.', 'capabilities' => ['inventory.advanced']],
            ['code' => 'accounting', 'name' => 'Contabilidad', 'kind' => 'addon', 'description' => 'Addon de contabilidad.', 'capabilities' => ['accounting.general_ledger']],
            ['code' => 'legacy_full', 'name' => 'Compatibilidad histórica', 'kind' => 'plan', 'description' => 'Plan interno para conservar capacidades existentes durante la migración SaaS.', 'capabilities' => ['sales.orders', 'sales.pos', 'sales.customers', 'sales.ecommerce', 'billing.electronic', 'inventory.advanced', 'accounting.general_ledger']],
        ];

        foreach ($plans as $plan) {
            DB::table('saas_plans')->updateOrInsert(
                ['code' => $plan['code']],
                [
                    'name' => $plan['name'],
                    'kind' => $plan['kind'],
                    'description' => $plan['description'],
                    'is_active' => true,
                    'metadata' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $planId = DB::table('saas_plans')->where('code', $plan['code'])->value('id');

            foreach ($plan['capabilities'] as $capabilityCode) {
                $capabilityId = DB::table('saas_capabilities')->where('code', $capabilityCode)->value('id');

                if ($planId && $capabilityId) {
                    DB::table('saas_plan_capabilities')->updateOrInsert(
                        ['plan_id' => $planId, 'capability_id' => $capabilityId],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        }
    }

    private function assignLegacyPlanToExistingOrganizations(): void
    {
        $legacyPlanId = DB::table('saas_plans')->where('code', 'legacy_full')->value('id');

        if (! $legacyPlanId) {
            return;
        }

        DB::table('organizations')->orderBy('id')->each(function (object $organization) use ($legacyPlanId): void {
            $exists = DB::table('organization_plan_subscriptions')
                ->where('organization_id', $organization->id)
                ->where('plan_id', $legacyPlanId)
                ->exists();

            if (! $exists) {
                DB::table('organization_plan_subscriptions')->insert([
                    'organization_id' => $organization->id,
                    'plan_id' => $legacyPlanId,
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => null,
                    'metadata' => json_encode(['source' => 'phase_01_legacy_backfill']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
};
