<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->string('account_deferred_revenue', 120)->nullable()->after('account_revenue'));
        Schema::table('categories', fn (Blueprint $t) => $t->string('account_deferred_revenue', 120)->nullable()->after('account_revenue'));
        Schema::table('accounting_settings', fn (Blueprint $t) => $t->string('default_account_deferred_revenue', 120)->nullable()->after('default_account_revenue'));

        Schema::create('customer_subscriptions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('security_branches')->restrictOnDelete();
            $t->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('source_order_item_id')->nullable()->constrained('order_items')->restrictOnDelete();
            $t->string('code', 40);
            $t->string('idempotency_key', 160);
            $t->char('payload_hash', 64);
            $t->string('status', 20)->default('active');
            $t->char('currency', 3);
            $t->unsignedSmallInteger('billing_cycle_months')->default(1);
            $t->bigInteger('recurring_subtotal_minor');
            $t->bigInteger('recurring_tax_minor')->default(0);
            $t->bigInteger('recurring_total_minor');
            $t->date('service_starts_on');
            $t->date('current_period_starts_on');
            $t->date('current_period_ends_on');
            $t->date('next_renewal_on');
            $t->date('ends_on')->nullable();
            $t->boolean('cancel_at_period_end')->default(false);
            $t->timestamp('cancelled_at')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->unsignedInteger('renewal_count')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['organization_id', 'code'], 'customer_subscriptions_org_code_unique');
            $t->unique(['organization_id', 'idempotency_key'], 'customer_subscriptions_org_key_unique');
            $t->index(['organization_id', 'status', 'next_renewal_on'], 'customer_subscriptions_renewal_idx');
        });

        Schema::create('subscription_service_periods', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $t->foreignId('subscription_id')->constrained('customer_subscriptions')->restrictOnDelete();
            $t->unsignedInteger('sequence');
            $t->string('status', 20)->default('scheduled');
            $t->string('idempotency_key', 160);
            $t->char('payload_hash', 64);
            $t->date('service_starts_on');
            $t->date('service_ends_on');
            $t->date('billing_due_on');
            $t->bigInteger('subtotal_minor');
            $t->bigInteger('tax_minor');
            $t->bigInteger('total_minor');
            $t->json('accounting_snapshot')->nullable();
            $t->foreignId('billing_document_id')->nullable()->constrained('billing_documents')->restrictOnDelete();
            $t->timestamp('renewed_at')->nullable();
            $t->timestamps();
            $t->unique(['subscription_id', 'sequence'], 'subscription_periods_sequence_unique');
            $t->unique(['organization_id', 'idempotency_key'], 'subscription_periods_org_key_unique');
            $t->unique('billing_document_id', 'subscription_periods_billing_unique');
        });

        Schema::create('subscription_accrual_schedules', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $t->foreignId('subscription_id')->constrained('customer_subscriptions')->restrictOnDelete();
            $t->foreignId('service_period_id')->constrained('subscription_service_periods')->restrictOnDelete();
            $t->unsignedInteger('sequence');
            $t->unsignedInteger('revision')->default(1);
            $t->string('kind', 20)->default('regular');
            $t->string('status', 24)->default('pending');
            $t->string('idempotency_key', 160);
            $t->char('payload_hash', 64);
            $t->date('service_starts_on');
            $t->date('service_ends_on');
            $t->date('due_on');
            $t->bigInteger('amount_minor');
            $t->char('currency', 3);
            $t->text('reason')->nullable();
            $t->foreignId('accounting_economic_event_id')->nullable()->constrained('accounting_economic_events')->restrictOnDelete();
            $t->uuid('lease_token')->nullable();
            $t->timestamp('claimed_at')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->string('error_code', 80)->nullable();
            $t->text('error_message')->nullable();
            $t->timestamp('event_recorded_at')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'idempotency_key'], 'subscription_accruals_org_key_unique');
            $t->unique('accounting_economic_event_id', 'subscription_accruals_event_unique');
            $t->unique(['service_period_id', 'sequence', 'revision'], 'subscription_accruals_slice_unique');
            $t->index(['organization_id', 'status', 'due_on'], 'subscription_accruals_due_idx');
        });

        $this->createTenantTriggers();
        $this->seedControlPlane();
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_subscriptions') && DB::table('customer_subscriptions')->exists()) {
            throw new RuntimeException('No se puede revertir FASE 09: existen suscripciones comerciales.');
        }
        $this->dropTenantTriggers();
        $moduleId = DB::table('security_modules')->where('code', 'subscriptions')->value('id');
        if ($moduleId) {
            DB::table('security_role_permissions')->whereIn('permission_id', DB::table('security_permissions')->where('module_id', $moduleId)->pluck('id'))->delete();
            DB::table('security_role_module_access')->where('module_id', $moduleId)->delete();
            DB::table('security_permissions')->where('module_id', $moduleId)->delete();
            DB::table('security_modules')->where('id', $moduleId)->delete();
        }
        $capabilityId = DB::table('saas_capabilities')->where('code', 'subscriptions.recurring')->value('id');
        if ($capabilityId) {
            DB::table('saas_plan_capabilities')->where('capability_id', $capabilityId)->delete();
            DB::table('organization_entitlements')->where('capability_id', $capabilityId)->delete();
            DB::table('saas_capabilities')->where('id', $capabilityId)->delete();
        }
        Schema::dropIfExists('subscription_accrual_schedules');
        Schema::dropIfExists('subscription_service_periods');
        Schema::dropIfExists('customer_subscriptions');
        Schema::table('accounting_settings', fn (Blueprint $t) => $t->dropColumn('default_account_deferred_revenue'));
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn('account_deferred_revenue'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('account_deferred_revenue'));
    }

    private function createTenantTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER customer_subscriptions_tenant_insert BEFORE INSERT ON customer_subscriptions WHEN NOT EXISTS (SELECT 1 FROM users u WHERE u.id=NEW.customer_id AND u.organization_id=NEW.organization_id) OR NOT EXISTS (SELECT 1 FROM products p WHERE p.id=NEW.product_id AND p.organization_id=NEW.organization_id) OR (NEW.branch_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM security_branches b WHERE b.id=NEW.branch_id AND b.organization_id=NEW.organization_id)) OR (NEW.source_order_item_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.id=NEW.source_order_item_id AND oi.organization_id=NEW.organization_id AND oi.product_id=NEW.product_id)) BEGIN SELECT RAISE(ABORT, 'subscription tenant scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER customer_subscriptions_tenant_update BEFORE UPDATE ON customer_subscriptions WHEN NOT EXISTS (SELECT 1 FROM users u WHERE u.id=NEW.customer_id AND u.organization_id=NEW.organization_id) OR NOT EXISTS (SELECT 1 FROM products p WHERE p.id=NEW.product_id AND p.organization_id=NEW.organization_id) OR (NEW.branch_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM security_branches b WHERE b.id=NEW.branch_id AND b.organization_id=NEW.organization_id)) OR (NEW.source_order_item_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.id=NEW.source_order_item_id AND oi.organization_id=NEW.organization_id AND oi.product_id=NEW.product_id)) BEGIN SELECT RAISE(ABORT, 'subscription tenant scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER subscription_periods_tenant_insert BEFORE INSERT ON subscription_service_periods WHEN NOT EXISTS (SELECT 1 FROM customer_subscriptions s WHERE s.id=NEW.subscription_id AND s.organization_id=NEW.organization_id) OR (NEW.billing_document_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM billing_documents d WHERE d.id=NEW.billing_document_id AND d.organization_id=NEW.organization_id)) BEGIN SELECT RAISE(ABORT, 'subscription period tenant scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER subscription_periods_tenant_update BEFORE UPDATE ON subscription_service_periods WHEN NOT EXISTS (SELECT 1 FROM customer_subscriptions s WHERE s.id=NEW.subscription_id AND s.organization_id=NEW.organization_id) OR (NEW.billing_document_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM billing_documents d WHERE d.id=NEW.billing_document_id AND d.organization_id=NEW.organization_id)) BEGIN SELECT RAISE(ABORT, 'subscription period tenant scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER subscription_accruals_tenant_insert BEFORE INSERT ON subscription_accrual_schedules WHEN NOT EXISTS (SELECT 1 FROM customer_subscriptions s WHERE s.id=NEW.subscription_id AND s.organization_id=NEW.organization_id) OR NOT EXISTS (SELECT 1 FROM subscription_service_periods p WHERE p.id=NEW.service_period_id AND p.organization_id=NEW.organization_id AND p.subscription_id=NEW.subscription_id) OR (NEW.accounting_economic_event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_economic_events e WHERE e.id=NEW.accounting_economic_event_id AND e.organization_id=NEW.organization_id)) BEGIN SELECT RAISE(ABORT, 'subscription accrual tenant scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER subscription_accruals_tenant_update BEFORE UPDATE ON subscription_accrual_schedules WHEN NOT EXISTS (SELECT 1 FROM customer_subscriptions s WHERE s.id=NEW.subscription_id AND s.organization_id=NEW.organization_id) OR NOT EXISTS (SELECT 1 FROM subscription_service_periods p WHERE p.id=NEW.service_period_id AND p.organization_id=NEW.organization_id AND p.subscription_id=NEW.subscription_id) OR (NEW.accounting_economic_event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_economic_events e WHERE e.id=NEW.accounting_economic_event_id AND e.organization_id=NEW.organization_id)) BEGIN SELECT RAISE(ABORT, 'subscription accrual tenant scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER subscription_accruals_recorded_immutable_update BEFORE UPDATE ON subscription_accrual_schedules WHEN OLD.status='event_recorded' AND (OLD.organization_id<>NEW.organization_id OR OLD.subscription_id<>NEW.subscription_id OR OLD.service_period_id<>NEW.service_period_id OR OLD.sequence<>NEW.sequence OR OLD.revision<>NEW.revision OR OLD.kind<>NEW.kind OR OLD.status<>NEW.status OR OLD.idempotency_key<>NEW.idempotency_key OR OLD.payload_hash<>NEW.payload_hash OR OLD.service_starts_on<>NEW.service_starts_on OR OLD.service_ends_on<>NEW.service_ends_on OR OLD.due_on<>NEW.due_on OR OLD.amount_minor<>NEW.amount_minor OR OLD.currency<>NEW.currency OR COALESCE(OLD.reason,'')<>COALESCE(NEW.reason,'') OR OLD.accounting_economic_event_id<>NEW.accounting_economic_event_id) BEGIN SELECT RAISE(ABORT, 'recorded subscription accrual is immutable'); END");
            DB::unprepared("CREATE TRIGGER subscription_accruals_recorded_immutable_delete BEFORE DELETE ON subscription_accrual_schedules WHEN OLD.status='event_recorded' BEGIN SELECT RAISE(ABORT, 'recorded subscription accrual is immutable'); END");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER customer_subscriptions_tenant_insert BEFORE INSERT ON customer_subscriptions FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM users u WHERE u.id=NEW.customer_id AND u.organization_id=NEW.organization_id) OR NOT EXISTS (SELECT 1 FROM products p WHERE p.id=NEW.product_id AND p.organization_id=NEW.organization_id) OR (NEW.branch_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM security_branches b WHERE b.id=NEW.branch_id AND b.organization_id=NEW.organization_id)) OR (NEW.source_order_item_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.id=NEW.source_order_item_id AND oi.organization_id=NEW.organization_id AND oi.product_id=NEW.product_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='subscription tenant scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER customer_subscriptions_tenant_update BEFORE UPDATE ON customer_subscriptions FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM users u WHERE u.id=NEW.customer_id AND u.organization_id=NEW.organization_id) OR NOT EXISTS (SELECT 1 FROM products p WHERE p.id=NEW.product_id AND p.organization_id=NEW.organization_id) OR (NEW.branch_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM security_branches b WHERE b.id=NEW.branch_id AND b.organization_id=NEW.organization_id)) OR (NEW.source_order_item_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.id=NEW.source_order_item_id AND oi.organization_id=NEW.organization_id AND oi.product_id=NEW.product_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='subscription tenant scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER subscription_periods_tenant_insert BEFORE INSERT ON subscription_service_periods FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM customer_subscriptions s WHERE s.id=NEW.subscription_id AND s.organization_id=NEW.organization_id) OR (NEW.billing_document_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM billing_documents d WHERE d.id=NEW.billing_document_id AND d.organization_id=NEW.organization_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='subscription period tenant scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER subscription_periods_tenant_update BEFORE UPDATE ON subscription_service_periods FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM customer_subscriptions s WHERE s.id=NEW.subscription_id AND s.organization_id=NEW.organization_id) OR (NEW.billing_document_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM billing_documents d WHERE d.id=NEW.billing_document_id AND d.organization_id=NEW.organization_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='subscription period tenant scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER subscription_accruals_tenant_insert BEFORE INSERT ON subscription_accrual_schedules FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM customer_subscriptions s WHERE s.id=NEW.subscription_id AND s.organization_id=NEW.organization_id) OR NOT EXISTS (SELECT 1 FROM subscription_service_periods p WHERE p.id=NEW.service_period_id AND p.organization_id=NEW.organization_id AND p.subscription_id=NEW.subscription_id) OR (NEW.accounting_economic_event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_economic_events e WHERE e.id=NEW.accounting_economic_event_id AND e.organization_id=NEW.organization_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='subscription accrual tenant scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER subscription_accruals_tenant_update BEFORE UPDATE ON subscription_accrual_schedules FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM customer_subscriptions s WHERE s.id=NEW.subscription_id AND s.organization_id=NEW.organization_id) OR NOT EXISTS (SELECT 1 FROM subscription_service_periods p WHERE p.id=NEW.service_period_id AND p.organization_id=NEW.organization_id AND p.subscription_id=NEW.subscription_id) OR (NEW.accounting_economic_event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_economic_events e WHERE e.id=NEW.accounting_economic_event_id AND e.organization_id=NEW.organization_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='subscription accrual tenant scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER subscription_accruals_recorded_immutable_update BEFORE UPDATE ON subscription_accrual_schedules FOR EACH ROW BEGIN IF OLD.status='event_recorded' AND (OLD.organization_id<>NEW.organization_id OR OLD.subscription_id<>NEW.subscription_id OR OLD.service_period_id<>NEW.service_period_id OR OLD.sequence<>NEW.sequence OR OLD.revision<>NEW.revision OR OLD.kind<>NEW.kind OR OLD.status<>NEW.status OR OLD.idempotency_key<>NEW.idempotency_key OR OLD.payload_hash<>NEW.payload_hash OR OLD.service_starts_on<>NEW.service_starts_on OR OLD.service_ends_on<>NEW.service_ends_on OR OLD.due_on<>NEW.due_on OR OLD.amount_minor<>NEW.amount_minor OR OLD.currency<>NEW.currency OR NOT (OLD.reason <=> NEW.reason) OR OLD.accounting_economic_event_id<>NEW.accounting_economic_event_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='recorded subscription accrual is immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER subscription_accruals_recorded_immutable_delete BEFORE DELETE ON subscription_accrual_schedules FOR EACH ROW BEGIN IF OLD.status='event_recorded' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='recorded subscription accrual is immutable'; END IF; END");
        }
    }

    private function dropTenantTriggers(): void
    {
        foreach (['customer_subscriptions_tenant_insert', 'customer_subscriptions_tenant_update', 'subscription_periods_tenant_insert', 'subscription_periods_tenant_update', 'subscription_accruals_tenant_insert', 'subscription_accruals_tenant_update', 'subscription_accruals_recorded_immutable_update', 'subscription_accruals_recorded_immutable_delete'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    private function seedControlPlane(): void
    {
        $now = now();
        DB::table('saas_capabilities')->updateOrInsert(['code' => 'subscriptions.recurring'], [
            'name' => 'Suscripciones recurrentes', 'description' => 'Contratos, renovaciones y devengamiento de suscripciones comerciales.',
            'is_technical_core' => false, 'is_active' => true, 'sort_order' => 110, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $capabilityId = DB::table('saas_capabilities')->where('code', 'subscriptions.recurring')->value('id');
        foreach (DB::table('saas_plans')->whereIn('code', ['enterprise', 'legacy_full'])->pluck('id') as $planId) {
            DB::table('saas_plan_capabilities')->updateOrInsert(['plan_id' => $planId, 'capability_id' => $capabilityId], ['created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('security_modules')->updateOrInsert(['code' => 'subscriptions'], [
            'name' => 'Suscripciones', 'description' => 'Contratos recurrentes y devengamiento', 'status' => 'implemented',
            'navigation_visible' => true, 'sort_order' => 75, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $moduleId = DB::table('security_modules')->where('code', 'subscriptions')->value('id');
        $permissionIds = [];
        foreach (['view', 'create', 'process', 'cancel', 'adjust'] as $action) {
            $code = "subscriptions.contracts.{$action}";
            DB::table('security_permissions')->updateOrInsert(['code' => $code], [
                'module_id' => $moduleId, 'resource' => 'contracts', 'action' => $action,
                'description' => $code, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $permissionIds[$action] = DB::table('security_permissions')->where('code', $code)->value('id');
        }
        foreach (DB::table('security_roles')->whereIn('code', ['super_admin', 'general_manager', 'sales_manager', 'billing_manager', 'accounting_manager'])->get() as $role) {
            $full = in_array($role->code, ['super_admin', 'sales_manager', 'billing_manager'], true);
            DB::table('security_role_module_access')->updateOrInsert(['role_id' => $role->id, 'module_id' => $moduleId], [
                'access_level' => $full ? 'full' : 'readonly', 'navigation_visible' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $actions = $full ? array_keys($permissionIds) : ['view'];
            foreach ($actions as $action) {
                DB::table('security_role_permissions')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $permissionIds[$action]], ['created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
};
