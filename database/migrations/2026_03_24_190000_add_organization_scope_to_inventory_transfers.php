<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_transfers') || ! Schema::hasTable('inventory_transfer_items')) {
            return;
        }

        Schema::table('inventory_transfers', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_transfers', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            }
        });

        Schema::table('inventory_transfer_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_transfer_items', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            }
        });

        $defaultOrganizationId = DB::table('organizations')->orderBy('id')->value('id');

        if ($defaultOrganizationId) {
            DB::table('inventory_transfers as transfers')
                ->leftJoin('security_branches as source_branch', 'source_branch.id', '=', 'transfers.source_branch_id')
                ->leftJoin('security_branches as destination_branch', 'destination_branch.id', '=', 'transfers.destination_branch_id')
                ->whereNull('transfers.organization_id')
                ->update([
                    'transfers.organization_id' => DB::raw('COALESCE(source_branch.organization_id, destination_branch.organization_id, '.$defaultOrganizationId.')'),
                ]);

            DB::table('inventory_transfer_items as items')
                ->join('inventory_transfers as transfers', 'transfers.id', '=', 'items.transfer_id')
                ->whereNull('items.organization_id')
                ->update([
                    'items.organization_id' => DB::raw('COALESCE(transfers.organization_id, '.$defaultOrganizationId.')'),
                ]);
        }

        Schema::table('inventory_transfers', function (Blueprint $table): void {
            $table->dropUnique('inventory_transfers_code_unique');
            $table->unique(['organization_id', 'code'], 'inventory_transfers_org_code_unique');
            $table->index(['organization_id', 'source_branch_id', 'destination_branch_id'], 'inventory_transfers_org_branches_idx');
        });

        Schema::table('inventory_transfer_items', function (Blueprint $table): void {
            $table->index(['organization_id', 'transfer_id'], 'inventory_transfer_items_org_transfer_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_transfers') || ! Schema::hasTable('inventory_transfer_items')) {
            return;
        }

        Schema::table('inventory_transfer_items', function (Blueprint $table): void {
            $table->dropIndex('inventory_transfer_items_org_transfer_idx');

            if (Schema::hasColumn('inventory_transfer_items', 'organization_id')) {
                $table->dropConstrainedForeignId('organization_id');
            }
        });

        Schema::table('inventory_transfers', function (Blueprint $table): void {
            $table->dropIndex('inventory_transfers_org_branches_idx');
            $table->dropUnique('inventory_transfers_org_code_unique');
            $table->unique('code');

            if (Schema::hasColumn('inventory_transfers', 'organization_id')) {
                $table->dropConstrainedForeignId('organization_id');
            }
        });
    }
};
