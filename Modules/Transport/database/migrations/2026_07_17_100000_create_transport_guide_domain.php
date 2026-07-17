<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete()->unique();
            $table->boolean('enabled')->default(false);
            $table->string('environment', 20)->default('simulation');
            $table->string('provider', 30)->default('simulation');
            $table->string('dispatch_mode', 20)->default('queue');
            $table->string('queue_connection', 60)->nullable();
            $table->string('queue_name', 60)->default('transport');
            $table->string('sender_series', 4)->default('T001');
            $table->string('carrier_series', 4)->default('V001');
            $table->boolean('allow_carrier_without_sender')->default(false);
            $table->text('provider_credentials')->nullable();
            $table->char('credentials_hash', 64)->nullable();
            $table->timestamp('credentials_validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transport_guide_counters', function (Blueprint $table): void {
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('guide_type', 20);
            $table->string('series', 4);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->primary(['organization_id', 'guide_type', 'series'], 'transport_guide_counters_primary');
        });

        Schema::create('transport_guides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('security_branches')->restrictOnDelete();
            $table->string('idempotency_key', 160);
            $table->char('payload_hash', 64);
            $table->string('guide_type', 20);
            $table->string('series', 4);
            $table->unsignedBigInteger('number');
            $table->string('status', 40)->default('draft');
            $table->string('reason_code', 2);
            $table->string('reason_catalog_version', 40);
            $table->string('transport_mode', 2);
            $table->date('issue_date');
            $table->dateTime('transfer_at');
            $table->json('origin_snapshot');
            $table->json('destination_snapshot');
            $table->json('recipient_snapshot');
            $table->json('transport_snapshot');
            $table->decimal('gross_weight', 14, 3);
            $table->string('weight_unit', 5)->default('KGM');
            $table->unsignedInteger('package_count')->nullable();
            $table->foreignId('inventory_document_id')->nullable()->constrained('inventory_documents')->restrictOnDelete();
            $table->foreignId('inventory_transfer_id')->nullable()->constrained('inventory_transfers')->restrictOnDelete();
            $table->foreignId('billing_document_id')->nullable()->constrained('billing_documents')->restrictOnDelete();
            $table->foreignId('related_guide_id')->nullable()->constrained('transport_guides')->restrictOnDelete();
            $table->json('external_sender_snapshot')->nullable();
            $table->text('exception_justification')->nullable();
            $table->string('provider', 30)->default('simulation');
            $table->string('environment', 20)->default('simulation');
            $table->char('provider_config_hash', 64);
            $table->string('provider_ticket', 160)->nullable();
            $table->string('provider_code', 30)->nullable();
            $table->text('provider_description')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('xml_disk', 30)->nullable();
            $table->string('xml_path')->nullable();
            $table->char('xml_hash', 64)->nullable();
            $table->string('cdr_disk', 30)->nullable();
            $table->string('cdr_path')->nullable();
            $table->char('cdr_hash', 64)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key'], 'transport_guides_org_idempotency_unique');
            $table->unique(['organization_id', 'guide_type', 'series', 'number'], 'transport_guides_org_number_unique');
            $table->index(['organization_id', 'status', 'created_at'], 'transport_guides_org_status_created_idx');
        });

        Schema::create('transport_guide_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('transport_guide_id')->constrained('transport_guides')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('code', 60);
            $table->string('description', 500);
            $table->decimal('quantity', 14, 4);
            $table->string('unit_code', 5)->default('NIU');
            $table->string('sunat_product_code', 30)->nullable();
            $table->timestamps();
            $table->unique(['transport_guide_id', 'line_number'], 'transport_guide_items_line_unique');
        });

        Schema::create('transport_guide_transmissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('transport_guide_id')->constrained('transport_guides')->restrictOnDelete();
            $table->string('idempotency_key', 190);
            $table->string('operation', 30);
            $table->string('status_before', 40)->nullable();
            $table->string('status_after', 40);
            $table->unsignedInteger('attempt_number');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['organization_id', 'idempotency_key'], 'transport_transmissions_org_key_unique');
            $table->index(['transport_guide_id', 'occurred_at'], 'transport_transmissions_guide_time_idx');
        });

        $this->createTriggers();
        $this->seedCapability();
        $this->seedSecurity();
    }

    public function down(): void
    {
        if (Schema::hasTable('transport_guides') && DB::table('transport_guides')->exists()) {
            throw new RuntimeException('No se puede revertir FASE 07 mientras existan guias de remision. La evidencia tributaria no se elimina.');
        }

        $this->dropTriggers();
        $this->removeCapability();
        $this->removeSecurity();
        Schema::dropIfExists('transport_guide_transmissions');
        Schema::dropIfExists('transport_guide_items');
        Schema::dropIfExists('transport_guides');
        Schema::dropIfExists('transport_guide_counters');
        Schema::dropIfExists('transport_settings');
    }

    private function createTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER transport_guides_tenant_insert BEFORE INSERT ON transport_guides WHEN NOT EXISTS (SELECT 1 FROM security_branches b WHERE b.id = NEW.branch_id AND b.organization_id = NEW.organization_id) OR (NEW.inventory_document_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_documents d WHERE d.id = NEW.inventory_document_id AND d.organization_id = NEW.organization_id)) OR (NEW.inventory_transfer_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_transfers t WHERE t.id = NEW.inventory_transfer_id AND t.organization_id = NEW.organization_id)) OR (NEW.billing_document_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM billing_documents d WHERE d.id = NEW.billing_document_id AND d.organization_id = NEW.organization_id)) OR (NEW.related_guide_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM transport_guides g WHERE g.id = NEW.related_guide_id AND g.organization_id = NEW.organization_id)) BEGIN SELECT RAISE(ABORT, 'transport guide tenant scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER transport_guides_tenant_update BEFORE UPDATE ON transport_guides WHEN NOT EXISTS (SELECT 1 FROM security_branches b WHERE b.id = NEW.branch_id AND b.organization_id = NEW.organization_id) OR (NEW.inventory_document_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_documents d WHERE d.id = NEW.inventory_document_id AND d.organization_id = NEW.organization_id)) OR (NEW.inventory_transfer_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_transfers t WHERE t.id = NEW.inventory_transfer_id AND t.organization_id = NEW.organization_id)) OR (NEW.billing_document_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM billing_documents d WHERE d.id = NEW.billing_document_id AND d.organization_id = NEW.organization_id)) OR (NEW.related_guide_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM transport_guides g WHERE g.id = NEW.related_guide_id AND g.organization_id = NEW.organization_id)) BEGIN SELECT RAISE(ABORT, 'transport guide tenant scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER transport_guide_items_tenant_insert BEFORE INSERT ON transport_guide_items WHEN NOT EXISTS (SELECT 1 FROM transport_guides g WHERE g.id = NEW.transport_guide_id AND g.organization_id = NEW.organization_id) OR (NEW.product_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products p WHERE p.id = NEW.product_id AND p.organization_id = NEW.organization_id)) BEGIN SELECT RAISE(ABORT, 'transport guide item tenant scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER transport_transmissions_tenant_insert BEFORE INSERT ON transport_guide_transmissions WHEN NOT EXISTS (SELECT 1 FROM transport_guides g WHERE g.id = NEW.transport_guide_id AND g.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'transport transmission tenant scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER transport_guide_items_immutable_update BEFORE UPDATE ON transport_guide_items BEGIN SELECT RAISE(ABORT, 'transport guide items are immutable'); END");
            DB::unprepared("CREATE TRIGGER transport_guide_items_immutable_delete BEFORE DELETE ON transport_guide_items BEGIN SELECT RAISE(ABORT, 'transport guide items are immutable'); END");
            DB::unprepared("CREATE TRIGGER transport_transmissions_immutable_update BEFORE UPDATE ON transport_guide_transmissions BEGIN SELECT RAISE(ABORT, 'transport transmissions are immutable'); END");
            DB::unprepared("CREATE TRIGGER transport_transmissions_immutable_delete BEFORE DELETE ON transport_guide_transmissions BEGIN SELECT RAISE(ABORT, 'transport transmissions are immutable'); END");

            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER transport_guides_tenant_insert BEFORE INSERT ON transport_guides FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM security_branches b WHERE b.id = NEW.branch_id AND b.organization_id = NEW.organization_id) OR (NEW.inventory_document_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_documents d WHERE d.id = NEW.inventory_document_id AND d.organization_id = NEW.organization_id)) OR (NEW.inventory_transfer_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_transfers t WHERE t.id = NEW.inventory_transfer_id AND t.organization_id = NEW.organization_id)) OR (NEW.billing_document_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM billing_documents d WHERE d.id = NEW.billing_document_id AND d.organization_id = NEW.organization_id)) OR (NEW.related_guide_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM transport_guides g WHERE g.id = NEW.related_guide_id AND g.organization_id = NEW.organization_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'transport guide tenant scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER transport_guides_tenant_update BEFORE UPDATE ON transport_guides FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM security_branches b WHERE b.id = NEW.branch_id AND b.organization_id = NEW.organization_id) OR (NEW.inventory_document_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_documents d WHERE d.id = NEW.inventory_document_id AND d.organization_id = NEW.organization_id)) OR (NEW.inventory_transfer_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_transfers t WHERE t.id = NEW.inventory_transfer_id AND t.organization_id = NEW.organization_id)) OR (NEW.billing_document_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM billing_documents d WHERE d.id = NEW.billing_document_id AND d.organization_id = NEW.organization_id)) OR (NEW.related_guide_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM transport_guides g WHERE g.id = NEW.related_guide_id AND g.organization_id = NEW.organization_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'transport guide tenant scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER transport_guide_items_tenant_insert BEFORE INSERT ON transport_guide_items FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM transport_guides g WHERE g.id = NEW.transport_guide_id AND g.organization_id = NEW.organization_id) OR (NEW.product_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products p WHERE p.id = NEW.product_id AND p.organization_id = NEW.organization_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'transport guide item tenant scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER transport_transmissions_tenant_insert BEFORE INSERT ON transport_guide_transmissions FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM transport_guides g WHERE g.id = NEW.transport_guide_id AND g.organization_id = NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'transport transmission tenant scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER transport_guide_items_immutable_update BEFORE UPDATE ON transport_guide_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'transport guide items are immutable'");
            DB::unprepared("CREATE TRIGGER transport_guide_items_immutable_delete BEFORE DELETE ON transport_guide_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'transport guide items are immutable'");
            DB::unprepared("CREATE TRIGGER transport_transmissions_immutable_update BEFORE UPDATE ON transport_guide_transmissions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'transport transmissions are immutable'");
            DB::unprepared("CREATE TRIGGER transport_transmissions_immutable_delete BEFORE DELETE ON transport_guide_transmissions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'transport transmissions are immutable'");
        }
    }

    private function dropTriggers(): void
    {
        foreach (['transport_guides_tenant_insert', 'transport_guides_tenant_update', 'transport_guide_items_tenant_insert', 'transport_transmissions_tenant_insert', 'transport_guide_items_immutable_update', 'transport_guide_items_immutable_delete', 'transport_transmissions_immutable_update', 'transport_transmissions_immutable_delete'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    private function seedSecurity(): void
    {
        if (! Schema::hasTable('security_modules') || ! Schema::hasTable('security_permissions')) {
            return;
        }
        DB::table('security_modules')->updateOrInsert(['code' => 'transport'], [
            'name' => 'Transporte y GRE', 'description' => 'Transporte y guias de remision electronicas',
            'status' => 'implemented', 'navigation_visible' => true, 'sort_order' => 165,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $moduleId = DB::table('security_modules')->where('code', 'transport')->value('id');
        foreach ([
            ['guides', 'view', 'transport.guides.view'],
            ['guides', 'create', 'transport.guides.create'],
            ['guides', 'submit', 'transport.guides.submit'],
            ['guides', 'poll', 'transport.guides.poll'],
            ['guides', 'export', 'transport.guides.export'],
            ['settings', 'configure', 'transport.settings.configure'],
        ] as [$resource, $action, $code]) {
            DB::table('security_permissions')->updateOrInsert(['code' => $code], [
                'module_id' => $moduleId, 'resource' => $resource, 'action' => $action,
                'description' => $code, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('security_roles') || ! Schema::hasTable('security_role_permissions') || ! Schema::hasTable('security_role_module_access')) {
            return;
        }
        $permissionIds = DB::table('security_permissions')->where('module_id', $moduleId)->pluck('id', 'code');
        foreach (['super_admin' => 'full', 'billing_manager' => 'full', 'warehouse_manager' => 'full', 'general_manager' => 'readonly'] as $roleCode => $access) {
            $roleId = DB::table('security_roles')->where('code', $roleCode)->value('id');
            if (! $roleId) {
                continue;
            }
            DB::table('security_role_module_access')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                ['access_level' => $access, 'navigation_visible' => true, 'created_at' => now(), 'updated_at' => now()],
            );
            $codes = match ($roleCode) {
                'general_manager' => ['transport.guides.view'],
                'warehouse_manager' => ['transport.guides.view', 'transport.guides.create', 'transport.guides.submit', 'transport.guides.poll', 'transport.guides.export'],
                default => $permissionIds->keys()->all(),
            };
            foreach ($codes as $code) {
                if (! isset($permissionIds[$code])) {
                    continue;
                }
                DB::table('security_role_permissions')->insertOrIgnore([
                    'role_id' => $roleId, 'permission_id' => $permissionIds[$code], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedCapability(): void
    {
        if (! Schema::hasTable('saas_capabilities')) {
            return;
        }
        DB::table('saas_capabilities')->updateOrInsert(['code' => 'transport.gre'], [
            'name' => 'Guias de remision electronicas',
            'description' => 'Emision y consulta de GRE remitente y transportista.',
            'is_technical_core' => false,
            'is_active' => true,
            'sort_order' => 95,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (! Schema::hasTable('saas_plans') || ! Schema::hasTable('saas_plan_capabilities')) {
            return;
        }
        $capabilityId = DB::table('saas_capabilities')->where('code', 'transport.gre')->value('id');
        foreach (DB::table('saas_plans')->whereIn('code', ['basic', 'enterprise', 'legacy_full'])->pluck('id') as $planId) {
            DB::table('saas_plan_capabilities')->insertOrIgnore([
                'plan_id' => $planId,
                'capability_id' => $capabilityId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function removeCapability(): void
    {
        if (! Schema::hasTable('saas_capabilities')) {
            return;
        }
        $capabilityId = DB::table('saas_capabilities')->where('code', 'transport.gre')->value('id');
        if (! $capabilityId) {
            return;
        }
        if (Schema::hasTable('organization_entitlements')) {
            DB::table('organization_entitlements')->where('capability_id', $capabilityId)->delete();
        }
        if (Schema::hasTable('saas_plan_capabilities')) {
            DB::table('saas_plan_capabilities')->where('capability_id', $capabilityId)->delete();
        }
        DB::table('saas_capabilities')->where('id', $capabilityId)->delete();
    }

    private function removeSecurity(): void
    {
        if (! Schema::hasTable('security_permissions')) {
            return;
        }
        $ids = DB::table('security_permissions')->where('code', 'like', 'transport.%')->pluck('id');
        if (Schema::hasTable('security_role_permissions') && $ids->isNotEmpty()) {
            DB::table('security_role_permissions')->whereIn('permission_id', $ids)->delete();
        }
        DB::table('security_permissions')->whereIn('id', $ids)->delete();
        if (Schema::hasTable('security_modules')) {
            DB::table('security_modules')->where('code', 'transport')->delete();
        }
    }
};
