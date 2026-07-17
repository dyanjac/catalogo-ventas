<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_inventory_channel_rollouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('channel', 30);
            $table->string('mode', 20)->default('legacy');
            $table->timestamp('activated_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'channel'], 'sales_inventory_rollouts_org_channel_unique');
        });

        Schema::create('sales_order_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('series', 10);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['organization_id', 'series'], 'sales_order_counters_org_series_unique');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('sales_channel', 30)->default('legacy')->after('branch_id');
            $table->string('idempotency_key', 160)->nullable()->after('sales_channel');
            $table->char('payload_hash', 64)->nullable()->after('idempotency_key');
            $table->foreignId('warehouse_id')->nullable()->after('payload_hash')->constrained('inventory_warehouses')->restrictOnDelete();
            $table->foreignId('inventory_reservation_id')->nullable()->after('warehouse_id')->constrained('inventory_reservations')->restrictOnDelete();
            $table->foreignId('dispatch_document_id')->nullable()->after('inventory_reservation_id')->constrained('inventory_documents')->restrictOnDelete();
            $table->foreignId('return_document_id')->nullable()->after('dispatch_document_id')->constrained('inventory_documents')->restrictOnDelete();
            $table->string('warehouse_status', 30)->default('legacy_completed')->after('status');
            $table->unsignedInteger('reservation_version')->default(0)->after('warehouse_status');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('dispatch_requested_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('return_requested_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unique(['organization_id', 'sales_channel', 'idempotency_key'], 'orders_org_channel_idempotency_unique');
            $table->unique('inventory_reservation_id', 'orders_inventory_reservation_unique');
            $table->unique('dispatch_document_id', 'orders_dispatch_document_unique');
            $table->unique('return_document_id', 'orders_return_document_unique');
            $table->index(['organization_id', 'sales_channel', 'warehouse_status'], 'orders_org_channel_warehouse_status_idx');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained('inventory_warehouses')->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->nullable()->after('warehouse_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->foreignId('inventory_reservation_item_id')->nullable()->after('inventory_balance_id')->constrained('inventory_reservation_items')->restrictOnDelete();
            $table->unsignedInteger('reserved_quantity')->default(0)->after('quantity');
            $table->unsignedInteger('dispatched_quantity')->default(0)->after('reserved_quantity');
            $table->unsignedInteger('returned_quantity')->default(0)->after('dispatched_quantity');
            $table->index(['organization_id', 'order_id'], 'order_items_org_order_idx');
        });

        DB::table('order_items')->whereNull('organization_id')->orderBy('id')->chunkById(500, function ($items): void {
            foreach ($items as $item) {
                DB::table('order_items')->where('id', $item->id)->update([
                    'organization_id' => DB::table('orders')->where('id', $item->order_id)->value('organization_id'),
                ]);
            }
        });

        if (Schema::hasTable('billing_documents')) {
            Schema::table('billing_documents', function (Blueprint $table): void {
                $table->foreignId('related_document_id')->nullable()->after('order_id')->constrained('billing_documents')->restrictOnDelete();
                $table->string('idempotency_key', 160)->nullable()->after('related_document_id');
                $table->char('payload_hash', 64)->nullable()->after('idempotency_key');
                $table->string('credit_note_reason_code', 10)->nullable()->after('document_type');
                $table->text('credit_note_reason')->nullable()->after('credit_note_reason_code');
                $table->timestamp('return_requested_at')->nullable()->after('voided_at');
                $table->index(['organization_id', 'related_document_id'], 'billing_documents_org_related_idx');
                $table->unique(['organization_id', 'idempotency_key'], 'billing_documents_org_idempotency_unique');
            });
        }

        $this->seedOperationalPermissions();
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'warehouse_status')) {
            $openOperations = DB::table('orders')
                ->whereIn('warehouse_status', ['reserved', 'dispatch_requested'])
                ->exists();
            $activeLinkedReservations = DB::table('inventory_reservations')
                ->join('orders', 'orders.inventory_reservation_id', '=', 'inventory_reservations.id')
                ->where('inventory_reservations.status', 'active')
                ->exists();
            if ($openOperations || $activeLinkedReservations) {
                throw new RuntimeException('No se puede revertir FASE 06 mientras existan reservas o despachos de venta abiertos.');
            }
        }

        $this->removeOperationalPermissions();

        if (Schema::hasTable('billing_documents')) {
            Schema::table('billing_documents', function (Blueprint $table): void {
                $table->dropIndex('billing_documents_org_related_idx');
                $table->dropUnique('billing_documents_org_idempotency_unique');
                $table->dropConstrainedForeignId('related_document_id');
                $table->dropColumn(['idempotency_key', 'payload_hash', 'credit_note_reason_code', 'credit_note_reason', 'return_requested_at']);
            });
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('order_items_org_order_idx');
            $table->dropConstrainedForeignId('inventory_reservation_item_id');
            $table->dropConstrainedForeignId('inventory_balance_id');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['reserved_quantity', 'dispatched_quantity', 'returned_quantity']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_org_channel_warehouse_status_idx');
            $table->dropUnique('orders_return_document_unique');
            $table->dropUnique('orders_dispatch_document_unique');
            $table->dropUnique('orders_inventory_reservation_unique');
            $table->dropUnique('orders_org_channel_idempotency_unique');
            $table->dropConstrainedForeignId('return_document_id');
            $table->dropConstrainedForeignId('dispatch_document_id');
            $table->dropConstrainedForeignId('inventory_reservation_id');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn([
                'sales_channel', 'idempotency_key', 'payload_hash', 'warehouse_status', 'reservation_version', 'reserved_at',
                'dispatch_requested_at', 'dispatched_at', 'return_requested_at', 'returned_at', 'cancelled_at',
            ]);
        });

        Schema::dropIfExists('sales_order_counters');
        Schema::dropIfExists('sales_inventory_channel_rollouts');
    }

    private function seedOperationalPermissions(): void
    {
        if (! Schema::hasTable('security_permissions') || ! Schema::hasTable('security_modules')) {
            return;
        }
        $moduleId = DB::table('security_modules')->where('code', 'inventory')->value('id');
        if (! $moduleId) {
            return;
        }

        foreach ([
            ['resource' => 'dispatches', 'action' => 'confirm', 'code' => 'inventory.dispatches.confirm'],
            ['resource' => 'returns', 'action' => 'confirm', 'code' => 'inventory.returns.confirm'],
        ] as $permission) {
            DB::table('security_permissions')->updateOrInsert(
                ['code' => $permission['code']],
                [
                    'module_id' => $moduleId,
                    'resource' => $permission['resource'],
                    'action' => $permission['action'],
                    'description' => $permission['code'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $warehouseRoleId = DB::table('security_roles')->where('code', 'warehouse_manager')->value('id');
        if (! $warehouseRoleId) {
            return;
        }
        $permissionIds = DB::table('security_permissions')
            ->whereIn('code', ['inventory.dispatches.confirm', 'inventory.returns.confirm'])
            ->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('security_role_permissions')->insertOrIgnore([
                'role_id' => $warehouseRoleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function removeOperationalPermissions(): void
    {
        if (! Schema::hasTable('security_permissions')) {
            return;
        }
        $permissionIds = DB::table('security_permissions')
            ->whereIn('code', ['inventory.dispatches.confirm', 'inventory.returns.confirm'])
            ->pluck('id');
        if (Schema::hasTable('security_role_permissions') && $permissionIds->isNotEmpty()) {
            DB::table('security_role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }
        DB::table('security_permissions')->whereIn('id', $permissionIds)->delete();
    }
};
