<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $triggers = [
        'accounting_lines_posted_insert_guard',
        'accounting_lines_tenant_update_guard',
        'accounting_entries_event_tenant_insert_guard',
        'accounting_entries_event_tenant_update_guard',
        'accounting_events_entry_tenant_update_guard',
        'accounting_events_reversal_tenant_insert_guard',
        'accounting_events_reversal_tenant_update_guard',
        'accounting_entries_reversal_tenant_insert_guard',
        'accounting_entries_reversal_tenant_update_guard',
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER accounting_lines_posted_insert_guard BEFORE INSERT ON accounting_entry_lines WHEN EXISTS (SELECT 1 FROM accounting_entries e WHERE e.id = NEW.accounting_entry_id AND e.status IN ('posted','voided')) BEGIN SELECT RAISE(ABORT, 'posted accounting entry lines are immutable'); END");
            DB::unprepared("CREATE TRIGGER accounting_lines_tenant_update_guard BEFORE UPDATE ON accounting_entry_lines WHEN NOT EXISTS (SELECT 1 FROM accounting_entries e WHERE e.id = NEW.accounting_entry_id AND e.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'accounting line tenant mismatch'); END");
            DB::unprepared("CREATE TRIGGER accounting_entries_event_tenant_insert_guard BEFORE INSERT ON accounting_entries WHEN NEW.economic_event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_economic_events ev WHERE ev.id = NEW.economic_event_id AND ev.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'accounting entry event tenant mismatch'); END");
            DB::unprepared("CREATE TRIGGER accounting_entries_event_tenant_update_guard BEFORE UPDATE ON accounting_entries WHEN NEW.economic_event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_economic_events ev WHERE ev.id = NEW.economic_event_id AND ev.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'accounting entry event tenant mismatch'); END");
            DB::unprepared("CREATE TRIGGER accounting_events_entry_tenant_update_guard BEFORE UPDATE ON accounting_economic_events WHEN NEW.processed_entry_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_entries e WHERE e.id = NEW.processed_entry_id AND e.organization_id = NEW.organization_id AND e.economic_event_id = NEW.id) BEGIN SELECT RAISE(ABORT, 'economic event entry tenant mismatch'); END");
            DB::unprepared("CREATE TRIGGER accounting_events_reversal_tenant_insert_guard BEFORE INSERT ON accounting_economic_events WHEN NEW.reversal_of_event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_economic_events original WHERE original.id = NEW.reversal_of_event_id AND original.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'economic event reversal tenant mismatch'); END");
            DB::unprepared("CREATE TRIGGER accounting_events_reversal_tenant_update_guard BEFORE UPDATE ON accounting_economic_events WHEN NEW.reversal_of_event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_economic_events original WHERE original.id = NEW.reversal_of_event_id AND original.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'economic event reversal tenant mismatch'); END");
            DB::unprepared("CREATE TRIGGER accounting_entries_reversal_tenant_insert_guard BEFORE INSERT ON accounting_entries WHEN NEW.reversal_of_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_entries original WHERE original.id = NEW.reversal_of_id AND original.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'accounting entry reversal tenant mismatch'); END");
            DB::unprepared("CREATE TRIGGER accounting_entries_reversal_tenant_update_guard BEFORE UPDATE ON accounting_entries WHEN NEW.reversal_of_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_entries original WHERE original.id = NEW.reversal_of_id AND original.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'accounting entry reversal tenant mismatch'); END");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER accounting_lines_posted_insert_guard BEFORE INSERT ON accounting_entry_lines FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM accounting_entries e WHERE e.id = NEW.accounting_entry_id AND e.status IN ('posted','voided')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'posted accounting entry lines are immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_lines_tenant_update_guard BEFORE UPDATE ON accounting_entry_lines FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM accounting_entries e WHERE e.id = NEW.accounting_entry_id AND e.organization_id = NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'accounting line tenant mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_entries_event_tenant_insert_guard BEFORE INSERT ON accounting_entries FOR EACH ROW BEGIN IF NEW.economic_event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_economic_events ev WHERE ev.id = NEW.economic_event_id AND ev.organization_id = NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'accounting entry event tenant mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_entries_event_tenant_update_guard BEFORE UPDATE ON accounting_entries FOR EACH ROW BEGIN IF NEW.economic_event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_economic_events ev WHERE ev.id = NEW.economic_event_id AND ev.organization_id = NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'accounting entry event tenant mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_events_entry_tenant_update_guard BEFORE UPDATE ON accounting_economic_events FOR EACH ROW BEGIN IF NEW.processed_entry_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_entries e WHERE e.id = NEW.processed_entry_id AND e.organization_id = NEW.organization_id AND e.economic_event_id = NEW.id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'economic event entry tenant mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_events_reversal_tenant_insert_guard BEFORE INSERT ON accounting_economic_events FOR EACH ROW BEGIN IF NEW.reversal_of_event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_economic_events original WHERE original.id = NEW.reversal_of_event_id AND original.organization_id = NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'economic event reversal tenant mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_events_reversal_tenant_update_guard BEFORE UPDATE ON accounting_economic_events FOR EACH ROW BEGIN IF NEW.reversal_of_event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_economic_events original WHERE original.id = NEW.reversal_of_event_id AND original.organization_id = NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'economic event reversal tenant mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_entries_reversal_tenant_insert_guard BEFORE INSERT ON accounting_entries FOR EACH ROW BEGIN IF NEW.reversal_of_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_entries original WHERE original.id = NEW.reversal_of_id AND original.organization_id = NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'accounting entry reversal tenant mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_entries_reversal_tenant_update_guard BEFORE UPDATE ON accounting_entries FOR EACH ROW BEGIN IF NEW.reversal_of_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM accounting_entries original WHERE original.id = NEW.reversal_of_id AND original.organization_id = NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'accounting entry reversal tenant mismatch'; END IF; END");
        }
    }

    public function down(): void
    {
        foreach ($this->triggers as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
