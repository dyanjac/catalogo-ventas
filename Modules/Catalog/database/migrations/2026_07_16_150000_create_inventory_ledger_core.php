<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('security_branches')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->restrictOnDelete();
            $table->string('location_type', 20);
            $table->string('location_key', 80);
            $table->integer('physical_stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->integer('in_transit_stock')->default(0);
            $table->integer('min_stock')->default(0);
            $table->decimal('average_cost', 14, 4)->default(0);
            $table->decimal('last_cost', 14, 4)->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['organization_id', 'product_id', 'location_key'],
                'inventory_balances_org_product_location_unique'
            );
            $table->index(['organization_id', 'branch_id', 'is_active'], 'inventory_balances_org_branch_active_idx');
            $table->index(['organization_id', 'warehouse_id', 'is_active'], 'inventory_balances_org_warehouse_active_idx');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreignId('inventory_balance_id')->nullable()->after('organization_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->string('idempotency_key', 160)->nullable()->after('movement_type');
            $table->char('payload_hash', 64)->nullable()->after('idempotency_key');
            $table->string('reason_code', 60)->nullable()->after('reason');
            $table->unsignedBigInteger('balance_version')->nullable()->after('stock_after');
            $table->foreignId('reversal_of_id')->nullable()->after('reference_id')->constrained('inventory_movements')->restrictOnDelete();
            $table->unsignedTinyInteger('ledger_generation')->nullable()->after('reversal_of_id');
            $table->timestamp('occurred_at')->nullable()->after('ledger_generation');

            $table->unique(['organization_id', 'idempotency_key'], 'inventory_movements_org_idempotency_unique');
            $table->unique(['inventory_balance_id', 'balance_version'], 'inventory_movements_balance_version_unique');
            $table->unique('reversal_of_id', 'inventory_movements_reversal_unique');
            $table->index(['organization_id', 'occurred_at'], 'inventory_movements_org_occurred_idx');
        });

        Schema::table('inventory_document_items', function (Blueprint $table): void {
            $table->integer('target_quantity')->nullable()->after('quantity');
        });

        Schema::create('inventory_ledger_rollouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained('organizations')->cascadeOnDelete();
            $table->string('mode', 20)->default('off');
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->json('last_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('checked_balances')->default(0);
            $table->unsignedInteger('issue_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_reconciliation_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('inventory_reconciliation_runs')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('security_branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
            $table->string('issue_type', 60);
            $table->string('severity', 20)->default('error');
            $table->decimal('expected_value', 18, 4)->nullable();
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'issue_type'], 'inventory_reconciliation_issues_org_type_idx');
        });

        $this->createImmutabilityTriggers();
    }

    public function down(): void
    {
        $this->dropImmutabilityTriggers();

        Schema::dropIfExists('inventory_reconciliation_issues');
        Schema::dropIfExists('inventory_reconciliation_runs');
        Schema::dropIfExists('inventory_ledger_rollouts');

        Schema::table('inventory_document_items', function (Blueprint $table): void {
            $table->dropColumn('target_quantity');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropIndex('inventory_movements_org_occurred_idx');
            $table->dropUnique('inventory_movements_reversal_unique');
            $table->dropUnique('inventory_movements_balance_version_unique');
            $table->dropUnique('inventory_movements_org_idempotency_unique');
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropConstrainedForeignId('inventory_balance_id');
            $table->dropColumn([
                'idempotency_key',
                'payload_hash',
                'reason_code',
                'balance_version',
                'ledger_generation',
                'occurred_at',
            ]);
        });

        Schema::dropIfExists('inventory_balances');
    }

    private function createImmutabilityTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER inventory_movements_immutable_update BEFORE UPDATE ON inventory_movements BEGIN SELECT RAISE(ABORT, 'inventory movements are immutable'); END");
            DB::unprepared("CREATE TRIGGER inventory_movements_immutable_delete BEFORE DELETE ON inventory_movements BEGIN SELECT RAISE(ABORT, 'inventory movements are immutable'); END");

            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER inventory_movements_immutable_update BEFORE UPDATE ON inventory_movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory movements are immutable'");
            DB::unprepared("CREATE TRIGGER inventory_movements_immutable_delete BEFORE DELETE ON inventory_movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'inventory movements are immutable'");
        }
    }

    private function dropImmutabilityTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_immutable_update');
        DB::unprepared('DROP TRIGGER IF EXISTS inventory_movements_immutable_delete');
    }
};
