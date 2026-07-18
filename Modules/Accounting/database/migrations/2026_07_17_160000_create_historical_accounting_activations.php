<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_activation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('status', 24)->default('simulated');
            $table->dateTime('cutoff_at');
            $table->dateTime('captured_through_at');
            $table->char('simulation_hash', 64);
            $table->string('confirmation_token', 24);
            $table->json('configuration_snapshot');
            $table->json('summary');
            $table->unsignedInteger('eligible_count')->default(0);
            $table->unsignedInteger('existing_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('error_code', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'confirmation_token'], 'acct_activation_run_token_unique');
            $table->index(['organization_id', 'status', 'created_at'], 'acct_activation_run_lookup');
        });

        Schema::create('accounting_activation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activation_run_id')->constrained('accounting_activation_runs')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('event_type', 50);
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('source_code')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->string('idempotency_key');
            $table->char('payload_hash', 64);
            $table->char('simulation_hash', 64);
            $table->string('status', 32);
            $table->unsignedSmallInteger('dependency_order')->default(100);
            $table->string('dependency_key')->nullable();
            $table->json('payload');
            $table->json('configuration_snapshot')->nullable();
            $table->json('issues')->nullable();
            $table->foreignId('accounting_economic_event_id')->nullable()->constrained('accounting_economic_events')->restrictOnDelete();
            $table->foreignId('accounting_entry_id')->nullable()->constrained('accounting_entries')->restrictOnDelete();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['activation_run_id', 'event_type', 'source_type', 'source_id'], 'acct_activation_item_source_unique');
            $table->index(['activation_run_id', 'status', 'dependency_order'], 'acct_activation_item_run_status');
            $table->index(['organization_id', 'event_type', 'source_type', 'source_id'], 'acct_activation_item_source_lookup');
        });

        $this->createTenantTriggers();
    }

    public function down(): void
    {
        if (Schema::hasTable('accounting_activation_runs') && DB::table('accounting_activation_runs')->exists()) {
            throw new RuntimeException('No se puede retirar FASE 10 porque existen evidencias de activación histórica.');
        }

        Schema::dropIfExists('accounting_activation_items');
        Schema::dropIfExists('accounting_activation_runs');
    }

    private function createTenantTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (['insert' => 'INSERT', 'update' => 'UPDATE'] as $suffix => $operation) {
                DB::unprepared("CREATE TRIGGER acct_activation_item_tenant_{$suffix}
                    BEFORE {$operation} ON accounting_activation_items
                    FOR EACH ROW
                    WHEN (SELECT organization_id FROM accounting_activation_runs WHERE id = NEW.activation_run_id) <> NEW.organization_id
                      OR (NEW.accounting_economic_event_id IS NOT NULL AND (SELECT organization_id FROM accounting_economic_events WHERE id = NEW.accounting_economic_event_id) <> NEW.organization_id)
                      OR (NEW.accounting_entry_id IS NOT NULL AND (SELECT organization_id FROM accounting_entries WHERE id = NEW.accounting_entry_id) <> NEW.organization_id)
                      OR (NEW.accounting_economic_event_id IS NOT NULL AND NEW.accounting_entry_id IS NOT NULL AND (SELECT economic_event_id FROM accounting_entries WHERE id = NEW.accounting_entry_id) <> NEW.accounting_economic_event_id)
                    BEGIN
                        SELECT RAISE(ABORT, 'accounting_activation_item_tenant_mismatch');
                    END");
            }
            DB::unprepared("CREATE TRIGGER acct_activation_item_immutable_update
                BEFORE UPDATE ON accounting_activation_items FOR EACH ROW
                WHEN OLD.activation_run_id IS NOT NEW.activation_run_id
                  OR OLD.organization_id IS NOT NEW.organization_id OR OLD.event_type IS NOT NEW.event_type
                  OR OLD.source_type IS NOT NEW.source_type OR OLD.source_id IS NOT NEW.source_id
                  OR OLD.source_code IS NOT NEW.source_code OR OLD.occurred_at IS NOT NEW.occurred_at
                  OR OLD.idempotency_key IS NOT NEW.idempotency_key OR OLD.payload_hash IS NOT NEW.payload_hash
                  OR OLD.simulation_hash IS NOT NEW.simulation_hash OR OLD.dependency_order IS NOT NEW.dependency_order
                  OR OLD.dependency_key IS NOT NEW.dependency_key OR OLD.payload IS NOT NEW.payload
                  OR OLD.configuration_snapshot IS NOT NEW.configuration_snapshot OR OLD.issues IS NOT NEW.issues
                BEGIN SELECT RAISE(ABORT, 'accounting_activation_item_immutable'); END");
            DB::unprepared("CREATE TRIGGER acct_activation_run_immutable_update
                BEFORE UPDATE ON accounting_activation_runs FOR EACH ROW
                WHEN OLD.status <> 'simulating' AND (
                  OLD.organization_id IS NOT NEW.organization_id OR OLD.cutoff_at IS NOT NEW.cutoff_at
                  OR OLD.captured_through_at IS NOT NEW.captured_through_at OR OLD.simulation_hash IS NOT NEW.simulation_hash
                  OR OLD.confirmation_token IS NOT NEW.confirmation_token OR OLD.configuration_snapshot IS NOT NEW.configuration_snapshot
                  OR OLD.summary IS NOT NEW.summary OR OLD.eligible_count IS NOT NEW.eligible_count
                  OR OLD.existing_count IS NOT NEW.existing_count OR OLD.error_count IS NOT NEW.error_count
                  OR OLD.created_by IS NOT NEW.created_by)
                BEGIN SELECT RAISE(ABORT, 'accounting_activation_run_immutable'); END");
            DB::unprepared("CREATE TRIGGER acct_activation_item_no_delete BEFORE DELETE ON accounting_activation_items
                BEGIN SELECT RAISE(ABORT, 'accounting_activation_item_no_delete'); END");
            DB::unprepared("CREATE TRIGGER acct_activation_run_no_delete BEFORE DELETE ON accounting_activation_runs
                BEGIN SELECT RAISE(ABORT, 'accounting_activation_run_no_delete'); END");

            return;
        }

        if (DB::getDriverName() === 'mysql') {
            foreach (['insert' => 'INSERT', 'update' => 'UPDATE'] as $suffix => $operation) {
                DB::unprepared("CREATE TRIGGER acct_activation_item_tenant_{$suffix}
                    BEFORE {$operation} ON accounting_activation_items FOR EACH ROW
                    BEGIN
                        IF (SELECT organization_id FROM accounting_activation_runs WHERE id = NEW.activation_run_id) <> NEW.organization_id
                           OR (NEW.accounting_economic_event_id IS NOT NULL AND (SELECT organization_id FROM accounting_economic_events WHERE id = NEW.accounting_economic_event_id) <> NEW.organization_id)
                           OR (NEW.accounting_entry_id IS NOT NULL AND (SELECT organization_id FROM accounting_entries WHERE id = NEW.accounting_entry_id) <> NEW.organization_id)
                           OR (NEW.accounting_economic_event_id IS NOT NULL AND NEW.accounting_entry_id IS NOT NULL AND (SELECT economic_event_id FROM accounting_entries WHERE id = NEW.accounting_entry_id) <> NEW.accounting_economic_event_id) THEN
                            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'accounting_activation_item_tenant_mismatch';
                        END IF;
                    END");
            }
            DB::unprepared("CREATE TRIGGER acct_activation_item_immutable_update
                BEFORE UPDATE ON accounting_activation_items FOR EACH ROW
                BEGIN
                    IF NOT (OLD.activation_run_id <=> NEW.activation_run_id) OR NOT (OLD.organization_id <=> NEW.organization_id)
                       OR NOT (OLD.event_type <=> NEW.event_type) OR NOT (OLD.source_type <=> NEW.source_type)
                       OR NOT (OLD.source_id <=> NEW.source_id) OR NOT (OLD.source_code <=> NEW.source_code)
                       OR NOT (OLD.occurred_at <=> NEW.occurred_at) OR NOT (OLD.idempotency_key <=> NEW.idempotency_key)
                       OR NOT (OLD.payload_hash <=> NEW.payload_hash) OR NOT (OLD.simulation_hash <=> NEW.simulation_hash)
                       OR NOT (OLD.dependency_order <=> NEW.dependency_order) OR NOT (OLD.dependency_key <=> NEW.dependency_key)
                       OR NOT (OLD.payload <=> NEW.payload) OR NOT (OLD.configuration_snapshot <=> NEW.configuration_snapshot)
                       OR NOT (OLD.issues <=> NEW.issues) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'accounting_activation_item_immutable';
                    END IF;
                END");
            DB::unprepared("CREATE TRIGGER acct_activation_run_immutable_update
                BEFORE UPDATE ON accounting_activation_runs FOR EACH ROW
                BEGIN
                    IF OLD.status <> 'simulating' AND (
                       NOT (OLD.organization_id <=> NEW.organization_id) OR NOT (OLD.cutoff_at <=> NEW.cutoff_at)
                       OR NOT (OLD.captured_through_at <=> NEW.captured_through_at) OR NOT (OLD.simulation_hash <=> NEW.simulation_hash)
                       OR NOT (OLD.confirmation_token <=> NEW.confirmation_token) OR NOT (OLD.configuration_snapshot <=> NEW.configuration_snapshot)
                       OR NOT (OLD.summary <=> NEW.summary) OR NOT (OLD.eligible_count <=> NEW.eligible_count)
                       OR NOT (OLD.existing_count <=> NEW.existing_count) OR NOT (OLD.error_count <=> NEW.error_count)
                       OR NOT (OLD.created_by <=> NEW.created_by)) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'accounting_activation_run_immutable';
                    END IF;
                END");
            DB::unprepared("CREATE TRIGGER acct_activation_item_no_delete BEFORE DELETE ON accounting_activation_items
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'accounting_activation_item_no_delete'");
            DB::unprepared("CREATE TRIGGER acct_activation_run_no_delete BEFORE DELETE ON accounting_activation_runs
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'accounting_activation_run_no_delete'");
        }
    }
};
