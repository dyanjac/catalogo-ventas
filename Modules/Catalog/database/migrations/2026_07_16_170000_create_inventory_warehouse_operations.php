<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_balances', function (Blueprint $table): void {
            $table->unsignedBigInteger('transit_version')->default(0)->after('reservation_version');
        });

        Schema::table('inventory_reservations', function (Blueprint $table): void {
            $table->timestamp('consumed_at')->nullable()->after('expired_at');
        });

        Schema::table('inventory_documents', function (Blueprint $table): void {
            $table->string('idempotency_key', 160)->nullable()->after('code');
            $table->char('payload_hash', 64)->nullable()->after('idempotency_key');
            $table->foreignId('reservation_id')->nullable()->after('warehouse_id')->constrained('inventory_reservations')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->after('reservation_id')->constrained('inventory_documents')->restrictOnDelete();
            $table->unique(['organization_id', 'idempotency_key'], 'inventory_documents_org_idempotency_unique');
            $table->unique('reversal_of_id', 'inventory_documents_reversal_unique');
        });

        Schema::table('inventory_document_items', function (Blueprint $table): void {
            $table->foreignId('inventory_balance_id')->nullable()->after('product_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->after('inventory_balance_id')->constrained('inventory_movements')->restrictOnDelete();
        });

        Schema::table('inventory_transfers', function (Blueprint $table): void {
            $table->string('idempotency_key', 160)->nullable()->after('code');
            $table->char('payload_hash', 64)->nullable()->after('idempotency_key');
            $table->foreignId('source_warehouse_id')->nullable()->after('destination_branch_id')->constrained('inventory_warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->nullable()->after('source_warehouse_id')->constrained('inventory_warehouses')->restrictOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['organization_id', 'idempotency_key'], 'inventory_transfers_org_idempotency_unique');
            $table->index(['organization_id', 'status', 'created_at'], 'inventory_transfers_org_status_created_idx');
        });

        Schema::table('inventory_transfer_items', function (Blueprint $table): void {
            $table->foreignId('source_balance_id')->nullable()->after('product_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->foreignId('destination_balance_id')->nullable()->after('source_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->unsignedInteger('dispatched_quantity')->default(0)->after('quantity');
            $table->unsignedInteger('received_quantity')->default(0)->after('dispatched_quantity');
            $table->decimal('unit_cost', 14, 4)->default(0)->after('received_quantity');
        });

        Schema::create('inventory_transfer_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('transfer_id')->constrained('inventory_transfers')->restrictOnDelete();
            $table->string('idempotency_key', 160);
            $table->char('payload_hash', 64);
            $table->string('event_type', 30);
            $table->string('status_before', 30)->nullable();
            $table->string('status_after', 30);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key'], 'inventory_transfer_events_org_key_unique');
            $table->index(['transfer_id', 'occurred_at'], 'inventory_transfer_events_transfer_time_idx');
        });

        Schema::create('inventory_transfer_event_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('event_id')->constrained('inventory_transfer_events')->restrictOnDelete();
            $table->foreignId('transfer_item_id')->constrained('inventory_transfer_items')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->integer('transit_delta')->default(0);
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'transfer_item_id'], 'inventory_transfer_event_items_unique');
        });

        $this->createTriggers();
        $this->createTenantScopeTriggers();
    }

    public function down(): void
    {
        $this->dropTriggers();
        Schema::dropIfExists('inventory_transfer_event_items');
        Schema::dropIfExists('inventory_transfer_events');

        Schema::table('inventory_transfer_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('destination_balance_id');
            $table->dropConstrainedForeignId('source_balance_id');
            $table->dropColumn(['dispatched_quantity', 'received_quantity', 'unit_cost']);
        });
        Schema::table('inventory_transfers', function (Blueprint $table): void {
            $table->dropIndex('inventory_transfers_org_status_created_idx');
            $table->dropUnique('inventory_transfers_org_idempotency_unique');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('completed_by');
            $table->dropConstrainedForeignId('dispatched_by');
            $table->dropConstrainedForeignId('destination_warehouse_id');
            $table->dropConstrainedForeignId('source_warehouse_id');
            $table->dropColumn(['idempotency_key', 'payload_hash', 'dispatched_at', 'completed_at', 'cancelled_at']);
        });
        Schema::table('inventory_document_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inventory_movement_id');
            $table->dropConstrainedForeignId('inventory_balance_id');
        });
        Schema::table('inventory_documents', function (Blueprint $table): void {
            $table->dropForeign(['reversal_of_id']);
            $table->dropUnique('inventory_documents_reversal_unique');
            $table->dropUnique('inventory_documents_org_idempotency_unique');
            $table->dropColumn('reversal_of_id');
            $table->dropConstrainedForeignId('reservation_id');
            $table->dropColumn(['idempotency_key', 'payload_hash']);
        });
        Schema::table('inventory_reservations', fn (Blueprint $table) => $table->dropColumn('consumed_at'));
        Schema::table('inventory_balances', fn (Blueprint $table) => $table->dropColumn('transit_version'));
    }

    private function createTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER inventory_balances_transit_invariant_insert BEFORE INSERT ON inventory_balances WHEN NEW.in_transit_stock < 0 BEGIN SELECT RAISE(ABORT, 'inventory transit invariant violated'); END");
            DB::unprepared("CREATE TRIGGER inventory_balances_transit_invariant_update BEFORE UPDATE ON inventory_balances WHEN NEW.in_transit_stock < 0 BEGIN SELECT RAISE(ABORT, 'inventory transit invariant violated'); END");
            DB::unprepared("CREATE TRIGGER inventory_transfer_events_immutable_update BEFORE UPDATE ON inventory_transfer_events BEGIN SELECT RAISE(ABORT, 'inventory transfer events are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_transfer_events_immutable_delete BEFORE DELETE ON inventory_transfer_events BEGIN SELECT RAISE(ABORT, 'inventory transfer events are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_transfer_event_items_immutable_update BEFORE UPDATE ON inventory_transfer_event_items BEGIN SELECT RAISE(ABORT, 'inventory transfer event items are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_transfer_event_items_immutable_delete BEFORE DELETE ON inventory_transfer_event_items BEGIN SELECT RAISE(ABORT, 'inventory transfer event items are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_transfer_events_tenant_insert BEFORE INSERT ON inventory_transfer_events WHEN NOT EXISTS (SELECT 1 FROM inventory_transfers t WHERE t.id = NEW.transfer_id AND t.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'inventory transfer event scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER inventory_transfer_event_items_tenant_insert BEFORE INSERT ON inventory_transfer_event_items WHEN NOT EXISTS (SELECT 1 FROM inventory_transfer_events e JOIN inventory_transfer_items i ON i.id = NEW.transfer_item_id WHERE e.id = NEW.event_id AND e.organization_id = NEW.organization_id AND i.organization_id = NEW.organization_id AND i.transfer_id = e.transfer_id) BEGIN SELECT RAISE(ABORT, 'inventory transfer event item scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER inventory_transfers_immutable_delete BEFORE DELETE ON inventory_transfers BEGIN SELECT RAISE(ABORT, 'inventory transfers are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_transfer_items_immutable_delete BEFORE DELETE ON inventory_transfer_items BEGIN SELECT RAISE(ABORT, 'inventory transfer items are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_documents_confirmed_immutable_update BEFORE UPDATE ON inventory_documents WHEN OLD.status = 'confirmed' BEGIN SELECT RAISE(ABORT, 'confirmed inventory documents are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_documents_confirmed_immutable_delete BEFORE DELETE ON inventory_documents WHEN OLD.status = 'confirmed' BEGIN SELECT RAISE(ABORT, 'confirmed inventory documents are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_document_items_confirmed_immutable_update BEFORE UPDATE ON inventory_document_items WHEN EXISTS (SELECT 1 FROM inventory_documents d WHERE d.id = OLD.document_id AND d.status = 'confirmed') BEGIN SELECT RAISE(ABORT, 'confirmed inventory document items are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_document_items_confirmed_immutable_delete BEFORE DELETE ON inventory_document_items WHEN EXISTS (SELECT 1 FROM inventory_documents d WHERE d.id = OLD.document_id AND d.status = 'confirmed') BEGIN SELECT RAISE(ABORT, 'confirmed inventory document items are immutable'); END");

            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER inventory_balances_transit_invariant_insert BEFORE INSERT ON inventory_balances FOR EACH ROW BEGIN IF NEW.in_transit_stock < 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory transit invariant violated'; END IF; END");
            DB::unprepared("CREATE TRIGGER inventory_balances_transit_invariant_update BEFORE UPDATE ON inventory_balances FOR EACH ROW BEGIN IF NEW.in_transit_stock < 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory transit invariant violated'; END IF; END");
            DB::unprepared("CREATE TRIGGER inventory_transfer_events_immutable_update BEFORE UPDATE ON inventory_transfer_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory transfer events are immutable'");
            DB::unprepared("CREATE TRIGGER inventory_transfer_events_immutable_delete BEFORE DELETE ON inventory_transfer_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory transfer events are immutable'");
            DB::unprepared("CREATE TRIGGER inventory_transfer_event_items_immutable_update BEFORE UPDATE ON inventory_transfer_event_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory transfer event items are immutable'");
            DB::unprepared("CREATE TRIGGER inventory_transfer_event_items_immutable_delete BEFORE DELETE ON inventory_transfer_event_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory transfer event items are immutable'");
            DB::unprepared("CREATE TRIGGER inventory_transfer_events_tenant_insert BEFORE INSERT ON inventory_transfer_events FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM inventory_transfers t WHERE t.id = NEW.transfer_id AND t.organization_id = NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory transfer event scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER inventory_transfer_event_items_tenant_insert BEFORE INSERT ON inventory_transfer_event_items FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM inventory_transfer_events e JOIN inventory_transfer_items i ON i.id = NEW.transfer_item_id WHERE e.id = NEW.event_id AND e.organization_id = NEW.organization_id AND i.organization_id = NEW.organization_id AND i.transfer_id = e.transfer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory transfer event item scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER inventory_transfers_immutable_delete BEFORE DELETE ON inventory_transfers FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory transfers are immutable'");
            DB::unprepared("CREATE TRIGGER inventory_transfer_items_immutable_delete BEFORE DELETE ON inventory_transfer_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory transfer items are immutable'");
            DB::unprepared("CREATE TRIGGER inventory_documents_confirmed_immutable_update BEFORE UPDATE ON inventory_documents FOR EACH ROW BEGIN IF OLD.status = 'confirmed' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'confirmed inventory documents are immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER inventory_documents_confirmed_immutable_delete BEFORE DELETE ON inventory_documents FOR EACH ROW BEGIN IF OLD.status = 'confirmed' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'confirmed inventory documents are immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER inventory_document_items_confirmed_immutable_update BEFORE UPDATE ON inventory_document_items FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM inventory_documents d WHERE d.id = OLD.document_id AND d.status = 'confirmed') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'confirmed inventory document items are immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER inventory_document_items_confirmed_immutable_delete BEFORE DELETE ON inventory_document_items FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM inventory_documents d WHERE d.id = OLD.document_id AND d.status = 'confirmed') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'confirmed inventory document items are immutable'; END IF; END");
        }
    }

    private function dropTriggers(): void
    {
        foreach ([
            'inventory_balances_transit_invariant_insert', 'inventory_balances_transit_invariant_update',
            'inventory_transfer_events_immutable_update', 'inventory_transfer_events_immutable_delete',
            'inventory_transfer_event_items_immutable_update', 'inventory_transfer_event_items_immutable_delete',
            'inventory_transfer_events_tenant_insert', 'inventory_transfer_event_items_tenant_insert',
            'inventory_transfers_immutable_delete', 'inventory_transfer_items_immutable_delete',
            'inventory_documents_confirmed_immutable_update', 'inventory_documents_confirmed_immutable_delete',
            'inventory_document_items_confirmed_immutable_update', 'inventory_document_items_confirmed_immutable_delete',
            'inventory_documents_tenant_insert', 'inventory_documents_tenant_update',
            'inventory_document_items_tenant_insert', 'inventory_document_items_tenant_update',
            'inventory_transfers_tenant_insert', 'inventory_transfers_tenant_update',
            'inventory_transfer_items_tenant_insert', 'inventory_transfer_items_tenant_update',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    private function createTenantScopeTriggers(): void
    {
        $checks = [
            'inventory_documents' => 'NOT EXISTS (SELECT 1 FROM security_branches b WHERE b.id = NEW.branch_id AND b.organization_id = NEW.organization_id) OR NOT EXISTS (SELECT 1 FROM inventory_warehouses w WHERE w.id = NEW.warehouse_id AND w.organization_id = NEW.organization_id AND w.branch_id = NEW.branch_id) OR (NEW.reservation_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_reservations r WHERE r.id = NEW.reservation_id AND r.organization_id = NEW.organization_id)) OR (NEW.reversal_of_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_documents d WHERE d.id = NEW.reversal_of_id AND d.organization_id = NEW.organization_id))',
            'inventory_document_items' => 'NOT EXISTS (SELECT 1 FROM inventory_documents d WHERE d.id = NEW.document_id AND d.organization_id = NEW.organization_id) OR NOT EXISTS (SELECT 1 FROM products p WHERE p.id = NEW.product_id AND p.organization_id = NEW.organization_id) OR (NEW.inventory_balance_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_balances b WHERE b.id = NEW.inventory_balance_id AND b.organization_id = NEW.organization_id AND b.product_id = NEW.product_id)) OR (NEW.inventory_movement_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_movements m JOIN inventory_documents d ON d.id = NEW.document_id WHERE m.id = NEW.inventory_movement_id AND m.organization_id = NEW.organization_id AND m.product_id = NEW.product_id AND m.branch_id = d.branch_id AND m.warehouse_id = d.warehouse_id))',
            'inventory_transfers' => 'NOT EXISTS (SELECT 1 FROM security_branches b WHERE b.id = NEW.source_branch_id AND b.organization_id = NEW.organization_id) OR NOT EXISTS (SELECT 1 FROM security_branches b WHERE b.id = NEW.destination_branch_id AND b.organization_id = NEW.organization_id) OR (NEW.source_warehouse_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_warehouses w WHERE w.id = NEW.source_warehouse_id AND w.organization_id = NEW.organization_id AND w.branch_id = NEW.source_branch_id)) OR (NEW.destination_warehouse_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_warehouses w WHERE w.id = NEW.destination_warehouse_id AND w.organization_id = NEW.organization_id AND w.branch_id = NEW.destination_branch_id))',
            'inventory_transfer_items' => 'NOT EXISTS (SELECT 1 FROM inventory_transfers t WHERE t.id = NEW.transfer_id AND t.organization_id = NEW.organization_id) OR NOT EXISTS (SELECT 1 FROM products p WHERE p.id = NEW.product_id AND p.organization_id = NEW.organization_id) OR (NEW.source_balance_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_balances b JOIN inventory_transfers t ON t.id = NEW.transfer_id WHERE b.id = NEW.source_balance_id AND b.organization_id = NEW.organization_id AND b.product_id = NEW.product_id AND b.warehouse_id = t.source_warehouse_id)) OR (NEW.destination_balance_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM inventory_balances b JOIN inventory_transfers t ON t.id = NEW.transfer_id WHERE b.id = NEW.destination_balance_id AND b.organization_id = NEW.organization_id AND b.product_id = NEW.product_id AND b.warehouse_id = t.destination_warehouse_id))',
        ];

        if (DB::getDriverName() === 'sqlite') {
            foreach ($checks as $table => $condition) {
                foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $operation => $suffix) {
                    DB::unprepared("CREATE TRIGGER {$table}_tenant_{$suffix} BEFORE {$operation} ON {$table} WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'inventory tenant scope mismatch'); END");
                }
            }

            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach ($checks as $table => $condition) {
                foreach (['INSERT' => 'insert', 'UPDATE' => 'update'] as $operation => $suffix) {
                    DB::unprepared("CREATE TRIGGER {$table}_tenant_{$suffix} BEFORE {$operation} ON {$table} FOR EACH ROW BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory tenant scope mismatch'; END IF; END");
                }
            }
        }
    }
};
