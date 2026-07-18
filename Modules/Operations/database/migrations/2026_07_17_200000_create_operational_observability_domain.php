<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->uuid('correlation_id')->unique();
            $table->string('trigger', 30)->default('manual');
            $table->string('status', 24)->default('running');
            $table->timestamp('captured_at');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->unsignedInteger('checked_inventory_balances')->default(0);
            $table->unsignedInteger('checked_inventory_documents')->default(0);
            $table->unsignedInteger('checked_economic_events')->default(0);
            $table->unsignedInteger('checked_accounting_entries')->default(0);
            $table->unsignedInteger('issue_count')->default(0);
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->json('metrics')->nullable();
            $table->string('error_code', 120)->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'started_at'], 'ops_runs_org_status_time_idx');
        });

        Schema::create('operational_reconciliation_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('operational_reconciliation_runs')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('domain', 40);
            $table->string('issue_code', 80);
            $table->string('severity', 20);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_code')->nullable();
            $table->char('fingerprint', 64);
            $table->json('expected')->nullable();
            $table->json('actual')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'fingerprint'], 'ops_issues_run_fingerprint_unique');
            $table->index(['organization_id', 'domain', 'severity'], 'ops_issues_org_domain_severity_idx');
        });

        Schema::create('operational_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->char('fingerprint', 64);
            $table->string('domain', 40);
            $table->string('issue_code', 80);
            $table->string('severity', 20);
            $table->string('status', 24)->default('open');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_code')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->foreignId('latest_run_id')->constrained('operational_reconciliation_runs')->restrictOnDelete();
            $table->json('context')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledgement_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'fingerprint'], 'ops_incidents_org_fingerprint_unique');
            $table->index(['organization_id', 'status', 'severity'], 'ops_incidents_org_status_severity_idx');
        });

        Schema::create('operational_incident_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained('operational_incidents')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('run_id')->nullable()->constrained('operational_reconciliation_runs')->restrictOnDelete();
            $table->string('event_type', 30);
            $table->json('context')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['organization_id', 'occurred_at'], 'ops_incident_events_org_time_idx');
        });

        $this->createTriggers();
    }

    public function down(): void
    {
        if (Schema::hasTable('operational_reconciliation_runs')
            && (DB::table('operational_reconciliation_runs')->exists() || DB::table('operational_incidents')->exists())) {
            throw new RuntimeException('No se puede retirar FASE 11 porque existe evidencia operativa.');
        }
        $this->dropTriggers();
        Schema::dropIfExists('operational_incident_events');
        Schema::dropIfExists('operational_incidents');
        Schema::dropIfExists('operational_reconciliation_issues');
        Schema::dropIfExists('operational_reconciliation_runs');
    }

    private function createTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER ops_run_identity_immutable BEFORE UPDATE ON operational_reconciliation_runs WHEN OLD.organization_id <> NEW.organization_id OR OLD.correlation_id <> NEW.correlation_id OR OLD.trigger <> NEW.trigger OR OLD.captured_at <> NEW.captured_at OR OLD.started_at <> NEW.started_at OR OLD.created_by IS NOT NEW.created_by BEGIN SELECT RAISE(ABORT, 'operational run identity is immutable'); END");
            DB::unprepared("CREATE TRIGGER ops_run_immutable_delete BEFORE DELETE ON operational_reconciliation_runs BEGIN SELECT RAISE(ABORT, 'operational runs are immutable'); END");
            DB::unprepared("CREATE TRIGGER ops_issue_tenant_insert BEFORE INSERT ON operational_reconciliation_issues WHEN NOT EXISTS (SELECT 1 FROM operational_reconciliation_runs r WHERE r.id=NEW.run_id AND r.organization_id=NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'operational issue tenant mismatch'); END");
            DB::unprepared("CREATE TRIGGER ops_issue_immutable_update BEFORE UPDATE ON operational_reconciliation_issues BEGIN SELECT RAISE(ABORT, 'operational issues are immutable'); END");
            DB::unprepared("CREATE TRIGGER ops_issue_immutable_delete BEFORE DELETE ON operational_reconciliation_issues BEGIN SELECT RAISE(ABORT, 'operational issues are immutable'); END");
            DB::unprepared("CREATE TRIGGER ops_incident_tenant_insert BEFORE INSERT ON operational_incidents WHEN NOT EXISTS (SELECT 1 FROM operational_reconciliation_runs r WHERE r.id=NEW.latest_run_id AND r.organization_id=NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'operational incident tenant mismatch'); END");
            DB::unprepared("CREATE TRIGGER ops_incident_identity_update BEFORE UPDATE ON operational_incidents WHEN OLD.organization_id <> NEW.organization_id OR OLD.fingerprint <> NEW.fingerprint OR OLD.domain <> NEW.domain OR OLD.issue_code <> NEW.issue_code OR OLD.source_type IS NOT NEW.source_type OR OLD.source_id IS NOT NEW.source_id OR NOT EXISTS (SELECT 1 FROM operational_reconciliation_runs r WHERE r.id=NEW.latest_run_id AND r.organization_id=NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'operational incident identity or tenant mismatch'); END");
            DB::unprepared("CREATE TRIGGER ops_incident_event_tenant_insert BEFORE INSERT ON operational_incident_events WHEN NOT EXISTS (SELECT 1 FROM operational_incidents i WHERE i.id=NEW.incident_id AND i.organization_id=NEW.organization_id) OR (NEW.run_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM operational_reconciliation_runs r WHERE r.id=NEW.run_id AND r.organization_id=NEW.organization_id)) BEGIN SELECT RAISE(ABORT, 'operational incident event tenant mismatch'); END");
            DB::unprepared("CREATE TRIGGER ops_incident_event_immutable_update BEFORE UPDATE ON operational_incident_events BEGIN SELECT RAISE(ABORT, 'operational incident events are immutable'); END");
            DB::unprepared("CREATE TRIGGER ops_incident_event_immutable_delete BEFORE DELETE ON operational_incident_events BEGIN SELECT RAISE(ABORT, 'operational incident events are immutable'); END");

            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER ops_run_identity_immutable BEFORE UPDATE ON operational_reconciliation_runs FOR EACH ROW BEGIN IF OLD.organization_id <> NEW.organization_id OR OLD.correlation_id <> NEW.correlation_id OR OLD.trigger <> NEW.trigger OR OLD.captured_at <> NEW.captured_at OR OLD.started_at <> NEW.started_at OR NOT (OLD.created_by <=> NEW.created_by) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='operational run identity is immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER ops_run_immutable_delete BEFORE DELETE ON operational_reconciliation_runs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='operational runs are immutable'");
            DB::unprepared("CREATE TRIGGER ops_issue_tenant_insert BEFORE INSERT ON operational_reconciliation_issues FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM operational_reconciliation_runs r WHERE r.id=NEW.run_id AND r.organization_id=NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='operational issue tenant mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER ops_issue_immutable_update BEFORE UPDATE ON operational_reconciliation_issues FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='operational issues are immutable'");
            DB::unprepared("CREATE TRIGGER ops_issue_immutable_delete BEFORE DELETE ON operational_reconciliation_issues FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='operational issues are immutable'");
            DB::unprepared("CREATE TRIGGER ops_incident_tenant_insert BEFORE INSERT ON operational_incidents FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM operational_reconciliation_runs r WHERE r.id=NEW.latest_run_id AND r.organization_id=NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='operational incident tenant mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER ops_incident_identity_update BEFORE UPDATE ON operational_incidents FOR EACH ROW BEGIN IF OLD.organization_id <> NEW.organization_id OR OLD.fingerprint <> NEW.fingerprint OR OLD.domain <> NEW.domain OR OLD.issue_code <> NEW.issue_code OR NOT (OLD.source_type <=> NEW.source_type) OR NOT (OLD.source_id <=> NEW.source_id) OR NOT EXISTS (SELECT 1 FROM operational_reconciliation_runs r WHERE r.id=NEW.latest_run_id AND r.organization_id=NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='operational incident identity or tenant mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER ops_incident_event_tenant_insert BEFORE INSERT ON operational_incident_events FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM operational_incidents i WHERE i.id=NEW.incident_id AND i.organization_id=NEW.organization_id) OR (NEW.run_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM operational_reconciliation_runs r WHERE r.id=NEW.run_id AND r.organization_id=NEW.organization_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='operational incident event tenant mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER ops_incident_event_immutable_update BEFORE UPDATE ON operational_incident_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='operational incident events are immutable'");
            DB::unprepared("CREATE TRIGGER ops_incident_event_immutable_delete BEFORE DELETE ON operational_incident_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='operational incident events are immutable'");
        }
    }

    private function dropTriggers(): void
    {
        foreach (['ops_run_identity_immutable', 'ops_run_immutable_delete', 'ops_issue_tenant_insert', 'ops_issue_immutable_update', 'ops_issue_immutable_delete', 'ops_incident_tenant_insert', 'ops_incident_identity_update', 'ops_incident_event_tenant_insert', 'ops_incident_event_immutable_update', 'ops_incident_event_immutable_delete'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
