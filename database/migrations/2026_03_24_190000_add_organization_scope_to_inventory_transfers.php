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
            DB::table('inventory_transfers')
                ->whereNull('organization_id')
                ->chunkById(200, function ($transfers) use ($defaultOrganizationId): void {
                    $branchIds = $transfers
                        ->flatMap(fn (object $transfer): array => [
                            $transfer->source_branch_id,
                            $transfer->destination_branch_id,
                        ])
                        ->filter()
                        ->unique()
                        ->all();
                    $branchOrganizations = DB::table('security_branches')
                        ->whereIn('id', $branchIds)
                        ->pluck('organization_id', 'id');

                    foreach ($transfers as $transfer) {
                        $organizationId = $branchOrganizations->get($transfer->source_branch_id)
                            ?? $branchOrganizations->get($transfer->destination_branch_id)
                            ?? $defaultOrganizationId;

                        DB::table('inventory_transfers')
                            ->where('id', $transfer->id)
                            ->update(['organization_id' => $organizationId]);
                    }
                });

            DB::table('inventory_transfer_items')
                ->whereNull('organization_id')
                ->chunkById(200, function ($items) use ($defaultOrganizationId): void {
                    $transferOrganizations = DB::table('inventory_transfers')
                        ->whereIn('id', $items->pluck('transfer_id')->filter()->all())
                        ->pluck('organization_id', 'id');

                    foreach ($items as $item) {
                        DB::table('inventory_transfer_items')
                            ->where('id', $item->id)
                            ->update([
                                'organization_id' => $transferOrganizations->get($item->transfer_id) ?? $defaultOrganizationId,
                            ]);
                    }
                });
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
