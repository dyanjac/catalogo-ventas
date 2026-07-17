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
            $table->unsignedBigInteger('reservation_version')->default(0)->after('version');
        });

        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('idempotency_key', 160);
            $table->char('payload_hash', 64);
            $table->string('status', 20)->default('active');
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_code', 120)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('terminal_actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key'], 'inventory_reservations_org_key_unique');
            $table->index(['organization_id', 'status', 'expires_at'], 'inventory_reservations_org_status_expiry_idx');
            $table->index(['organization_id', 'source_type', 'source_id'], 'inventory_reservations_org_source_idx');
        });

        Schema::create('inventory_reservation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('reservation_id')->constrained('inventory_reservations')->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('security_branches')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['reservation_id', 'inventory_balance_id'], 'inventory_reservation_items_balance_unique');
            $table->index(['organization_id', 'inventory_balance_id'], 'inventory_reservation_items_org_balance_idx');
        });

        Schema::create('inventory_reservation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('reservation_id')->constrained('inventory_reservations')->restrictOnDelete();
            $table->string('idempotency_key', 160);
            $table->char('payload_hash', 64);
            $table->string('event_type', 20);
            $table->string('status_before', 20)->nullable();
            $table->string('status_after', 20);
            $table->bigInteger('quantity_delta');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key'], 'inventory_reservation_events_org_key_unique');
            $table->index(['reservation_id', 'occurred_at'], 'inventory_reservation_events_reservation_time_idx');
        });

        $this->createInvariantTriggers();
    }

    public function down(): void
    {
        $this->dropInvariantTriggers();
        Schema::dropIfExists('inventory_reservation_events');
        Schema::dropIfExists('inventory_reservation_items');
        Schema::dropIfExists('inventory_reservations');
        Schema::table('inventory_balances', fn (Blueprint $table) => $table->dropColumn('reservation_version'));
    }

    private function createInvariantTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER inventory_balances_reservation_invariant_insert BEFORE INSERT ON inventory_balances WHEN NEW.reserved_stock < 0 OR NEW.physical_stock < NEW.reserved_stock BEGIN SELECT RAISE(ABORT, 'inventory balance reservation invariant violated'); END");
            DB::unprepared("CREATE TRIGGER inventory_balances_reservation_invariant_update BEFORE UPDATE ON inventory_balances WHEN NEW.reserved_stock < 0 OR NEW.physical_stock < NEW.reserved_stock BEGIN SELECT RAISE(ABORT, 'inventory balance reservation invariant violated'); END");
            DB::unprepared("CREATE TRIGGER inventory_reservation_items_immutable_update BEFORE UPDATE ON inventory_reservation_items BEGIN SELECT RAISE(ABORT, 'inventory reservation items are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_reservation_items_immutable_delete BEFORE DELETE ON inventory_reservation_items BEGIN SELECT RAISE(ABORT, 'inventory reservation items are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_reservation_events_immutable_update BEFORE UPDATE ON inventory_reservation_events BEGIN SELECT RAISE(ABORT, 'inventory reservation events are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_reservation_events_immutable_delete BEFORE DELETE ON inventory_reservation_events BEGIN SELECT RAISE(ABORT, 'inventory reservation events are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_reservation_items_tenant_insert BEFORE INSERT ON inventory_reservation_items WHEN NOT EXISTS (SELECT 1 FROM inventory_reservations r JOIN inventory_balances b ON b.id = NEW.inventory_balance_id WHERE r.id = NEW.reservation_id AND r.organization_id = NEW.organization_id AND b.organization_id = NEW.organization_id AND b.product_id = NEW.product_id AND b.branch_id = NEW.branch_id AND COALESCE(b.warehouse_id, 0) = COALESCE(NEW.warehouse_id, 0)) BEGIN SELECT RAISE(ABORT, 'inventory reservation item scope mismatch'); END");
            DB::unprepared("CREATE TRIGGER inventory_reservation_events_tenant_insert BEFORE INSERT ON inventory_reservation_events WHEN NOT EXISTS (SELECT 1 FROM inventory_reservations r WHERE r.id = NEW.reservation_id AND r.organization_id = NEW.organization_id) BEGIN SELECT RAISE(ABORT, 'inventory reservation event scope mismatch'); END");

            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER inventory_balances_reservation_invariant_insert BEFORE INSERT ON inventory_balances FOR EACH ROW BEGIN IF NEW.reserved_stock < 0 OR NEW.physical_stock < NEW.reserved_stock THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory balance reservation invariant violated'; END IF; END");
            DB::unprepared("CREATE TRIGGER inventory_balances_reservation_invariant_update BEFORE UPDATE ON inventory_balances FOR EACH ROW BEGIN IF NEW.reserved_stock < 0 OR NEW.physical_stock < NEW.reserved_stock THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory balance reservation invariant violated'; END IF; END");
            DB::unprepared("CREATE TRIGGER inventory_reservation_items_immutable_update BEFORE UPDATE ON inventory_reservation_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory reservation items are immutable'");
            DB::unprepared("CREATE TRIGGER inventory_reservation_items_immutable_delete BEFORE DELETE ON inventory_reservation_items FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory reservation items are immutable'");
            DB::unprepared("CREATE TRIGGER inventory_reservation_events_immutable_update BEFORE UPDATE ON inventory_reservation_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory reservation events are immutable'");
            DB::unprepared("CREATE TRIGGER inventory_reservation_events_immutable_delete BEFORE DELETE ON inventory_reservation_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory reservation events are immutable'");
            DB::unprepared("CREATE TRIGGER inventory_reservation_items_tenant_insert BEFORE INSERT ON inventory_reservation_items FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM inventory_reservations r JOIN inventory_balances b ON b.id = NEW.inventory_balance_id WHERE r.id = NEW.reservation_id AND r.organization_id = NEW.organization_id AND b.organization_id = NEW.organization_id AND b.product_id = NEW.product_id AND b.branch_id = NEW.branch_id AND COALESCE(b.warehouse_id, 0) = COALESCE(NEW.warehouse_id, 0)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory reservation item scope mismatch'; END IF; END");
            DB::unprepared("CREATE TRIGGER inventory_reservation_events_tenant_insert BEFORE INSERT ON inventory_reservation_events FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM inventory_reservations r WHERE r.id = NEW.reservation_id AND r.organization_id = NEW.organization_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory reservation event scope mismatch'; END IF; END");
        }
    }

    private function dropInvariantTriggers(): void
    {
        foreach ([
            'inventory_balances_reservation_invariant_insert',
            'inventory_balances_reservation_invariant_update',
            'inventory_reservation_items_immutable_update',
            'inventory_reservation_items_immutable_delete',
            'inventory_reservation_events_immutable_update',
            'inventory_reservation_events_immutable_delete',
            'inventory_reservation_items_tenant_insert',
            'inventory_reservation_events_tenant_insert',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
