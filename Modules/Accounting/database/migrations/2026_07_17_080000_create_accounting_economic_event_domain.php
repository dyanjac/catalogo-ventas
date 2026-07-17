<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_economic_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('security_branches')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('status', 20)->default('pending');
            $table->string('idempotency_key', 160);
            $table->char('payload_hash', 64);
            $table->string('source_type', 190);
            $table->unsignedBigInteger('source_id');
            $table->string('source_code', 120)->nullable();
            $table->json('payload');
            $table->json('configuration_snapshot')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_event_id')->nullable()->constrained('accounting_economic_events')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key'], 'accounting_events_org_key_unique');
            $table->unique(['organization_id', 'event_type', 'source_type', 'source_id'], 'accounting_events_org_source_unique');
            $table->unique('reversal_of_event_id', 'accounting_events_reversal_unique');
            $table->index(['organization_id', 'status', 'occurred_at'], 'accounting_events_org_status_time_idx');
        });

        Schema::table('accounting_entries', function (Blueprint $table): void {
            $table->foreignId('economic_event_id')->nullable()->after('organization_id')->constrained('accounting_economic_events')->restrictOnDelete();
            $table->string('origin', 30)->default('manual')->after('economic_event_id');
            $table->foreignId('reversal_of_id')->nullable()->after('origin')->constrained('accounting_entries')->restrictOnDelete();
            $table->char('payload_hash', 64)->nullable()->after('reversal_of_id');
            $table->unique('economic_event_id', 'accounting_entries_event_unique');
            $table->unique('reversal_of_id', 'accounting_entries_reversal_unique');
        });

        Schema::table('accounting_economic_events', function (Blueprint $table): void {
            $table->foreignId('processed_entry_id')->nullable()->after('configuration_snapshot')->constrained('accounting_entries')->restrictOnDelete();
            $table->unique('processed_entry_id', 'accounting_events_entry_unique');
        });

        Schema::table('accounting_settings', function (Blueprint $table): void {
            $table->string('default_account_cash', 120)->nullable()->after('default_account_tax');
        });

        $this->createTriggers();
    }

    public function down(): void
    {
        if (DB::table('accounting_economic_events')->exists()
            || DB::table('accounting_entries')->whereNotNull('economic_event_id')->exists()) {
            throw new \RuntimeException('No se puede revertir FASE 08: existen eventos económicos o asientos derivados.');
        }

        $this->dropTriggers();

        Schema::table('accounting_economic_events', function (Blueprint $table): void {
            $table->dropUnique('accounting_events_entry_unique');
            $table->dropConstrainedForeignId('processed_entry_id');
        });
        Schema::table('accounting_entries', function (Blueprint $table): void {
            $table->dropUnique('accounting_entries_reversal_unique');
            $table->dropUnique('accounting_entries_event_unique');
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropConstrainedForeignId('economic_event_id');
            $table->dropColumn(['origin', 'payload_hash']);
        });
        Schema::table('accounting_settings', fn (Blueprint $table) => $table->dropColumn('default_account_cash'));
        Schema::dropIfExists('accounting_economic_events');
    }

    private function createTriggers(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER accounting_events_identity_immutable BEFORE UPDATE ON accounting_economic_events WHEN OLD.organization_id <> NEW.organization_id OR OLD.event_type <> NEW.event_type OR OLD.idempotency_key <> NEW.idempotency_key OR OLD.payload_hash <> NEW.payload_hash OR OLD.source_type <> NEW.source_type OR OLD.source_id <> NEW.source_id OR OLD.payload <> NEW.payload BEGIN SELECT RAISE(ABORT, 'economic event identity is immutable'); END");
            DB::unprepared("CREATE TRIGGER accounting_events_immutable_delete BEFORE DELETE ON accounting_economic_events BEGIN SELECT RAISE(ABORT, 'economic events are immutable'); END");
            DB::unprepared("CREATE TRIGGER accounting_entries_posted_immutable_update BEFORE UPDATE ON accounting_entries WHEN OLD.status IN ('posted','voided') BEGIN SELECT RAISE(ABORT, 'posted accounting entries are immutable'); END");
            DB::unprepared("CREATE TRIGGER accounting_entries_posted_immutable_delete BEFORE DELETE ON accounting_entries WHEN OLD.status IN ('posted','voided') BEGIN SELECT RAISE(ABORT, 'posted accounting entries are immutable'); END");
            DB::unprepared("CREATE TRIGGER accounting_lines_posted_immutable_update BEFORE UPDATE ON accounting_entry_lines WHEN EXISTS (SELECT 1 FROM accounting_entries e WHERE e.id = OLD.accounting_entry_id AND e.status IN ('posted','voided')) BEGIN SELECT RAISE(ABORT, 'posted accounting entry lines are immutable'); END");
            DB::unprepared("CREATE TRIGGER accounting_lines_posted_immutable_delete BEFORE DELETE ON accounting_entry_lines WHEN EXISTS (SELECT 1 FROM accounting_entries e WHERE e.id = OLD.accounting_entry_id AND e.status IN ('posted','voided')) BEGIN SELECT RAISE(ABORT, 'posted accounting entry lines are immutable'); END");
            DB::unprepared("CREATE TRIGGER accounting_lines_tenant_insert BEFORE INSERT ON accounting_entry_lines WHEN NOT EXISTS (SELECT 1 FROM accounting_entries e WHERE e.id = NEW.accounting_entry_id AND e.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'accounting line tenant mismatch'); END");
        } elseif ($driver === 'mysql') {
            DB::unprepared("CREATE TRIGGER accounting_events_identity_immutable BEFORE UPDATE ON accounting_economic_events FOR EACH ROW BEGIN IF OLD.organization_id <> NEW.organization_id OR OLD.event_type <> NEW.event_type OR OLD.idempotency_key <> NEW.idempotency_key OR OLD.payload_hash <> NEW.payload_hash OR OLD.source_type <> NEW.source_type OR OLD.source_id <> NEW.source_id OR OLD.payload <> NEW.payload THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'economic event identity is immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_events_immutable_delete BEFORE DELETE ON accounting_economic_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'economic events are immutable'");
            DB::unprepared("CREATE TRIGGER accounting_entries_posted_immutable_update BEFORE UPDATE ON accounting_entries FOR EACH ROW BEGIN IF OLD.status IN ('posted','voided') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'posted accounting entries are immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_entries_posted_immutable_delete BEFORE DELETE ON accounting_entries FOR EACH ROW BEGIN IF OLD.status IN ('posted','voided') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'posted accounting entries are immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_lines_posted_immutable_update BEFORE UPDATE ON accounting_entry_lines FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM accounting_entries e WHERE e.id = OLD.accounting_entry_id AND e.status IN ('posted','voided')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'posted accounting entry lines are immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_lines_posted_immutable_delete BEFORE DELETE ON accounting_entry_lines FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM accounting_entries e WHERE e.id = OLD.accounting_entry_id AND e.status IN ('posted','voided')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'posted accounting entry lines are immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_lines_tenant_insert BEFORE INSERT ON accounting_entry_lines FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM accounting_entries e WHERE e.id = NEW.accounting_entry_id AND e.organization_id = NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'accounting line tenant mismatch'; END IF; END");
        }
    }

    private function dropTriggers(): void
    {
        foreach (['accounting_events_identity_immutable', 'accounting_events_immutable_delete', 'accounting_entries_posted_immutable_update', 'accounting_entries_posted_immutable_delete', 'accounting_lines_posted_immutable_update', 'accounting_lines_posted_immutable_delete', 'accounting_lines_tenant_insert'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
