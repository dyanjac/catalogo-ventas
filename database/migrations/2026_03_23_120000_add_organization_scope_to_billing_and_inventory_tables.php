<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addOrganizationIdColumn('billing_documents', 'order_id');
        $this->addOrganizationIdColumn('billing_document_files', 'billing_document_id');
        $this->addOrganizationIdColumn('billing_document_response_histories', 'billing_document_id');
        $this->addOrganizationIdColumn('product_branch_stocks', 'product_id');
        $this->addOrganizationIdColumn('inventory_movements', 'product_id');
        $this->addOrganizationIdColumn('inventory_warehouses', 'branch_id');
        $this->addOrganizationIdColumn('product_warehouse_stocks', 'product_id');
        $this->addOrganizationIdColumn('inventory_documents', 'code');
        $this->addOrganizationIdColumn('inventory_document_items', 'document_id');

        $this->backfillBillingDocuments();
        $this->backfillBillingDocumentFiles();
        $this->backfillBillingDocumentResponseHistories();
        $this->backfillInventoryTables();

        $this->rebuildSecurityBranchUnique();
        $this->rebuildBillingDocumentsUnique();
        $this->rebuildInventoryDocumentsUnique();
    }

    public function down(): void
    {
        $this->rollbackInventoryDocumentsUnique();
        $this->rollbackBillingDocumentsUnique();
        $this->rollbackSecurityBranchUnique();

        $this->dropOrganizationIdColumn('inventory_document_items');
        $this->dropOrganizationIdColumn('inventory_documents');
        $this->dropOrganizationIdColumn('product_warehouse_stocks');
        $this->dropOrganizationIdColumn('inventory_warehouses');
        $this->dropOrganizationIdColumn('inventory_movements');
        $this->dropOrganizationIdColumn('product_branch_stocks');
        $this->dropOrganizationIdColumn('billing_document_response_histories');
        $this->dropOrganizationIdColumn('billing_document_files');
        $this->dropOrganizationIdColumn('billing_documents');
    }

    private function addOrganizationIdColumn(string $table, string $after): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'organization_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $after): void {
            $blueprint->foreignId('organization_id')
                ->nullable()
                ->after($after)
                ->constrained('organizations')
                ->nullOnDelete();
            $blueprint->index(['organization_id'], $table.'_organization_id_idx');
        });
    }

    private function dropOrganizationIdColumn(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $blueprint->dropIndex($table.'_organization_id_idx');
            $blueprint->dropConstrainedForeignId('organization_id');
        });
    }

    private function backfillBillingDocuments(): void
    {
        if (! Schema::hasTable('billing_documents')) {
            return;
        }

        $orderOrganizations = Schema::hasTable('orders')
            ? DB::table('orders')->pluck('organization_id', 'id')
            : collect();
        $branchOrganizations = Schema::hasTable('security_branches')
            ? DB::table('security_branches')->pluck('organization_id', 'id')
            : collect();

        DB::table('billing_documents')
            ->select('id', 'order_id', 'branch_id', 'organization_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $document) use ($orderOrganizations, $branchOrganizations): void {
                if ($document->organization_id) {
                    return;
                }

                $organizationId = $document->order_id
                    ? ($orderOrganizations[$document->order_id] ?? null)
                    : null;

                if (! $organizationId && $document->branch_id) {
                    $organizationId = $branchOrganizations[$document->branch_id] ?? null;
                }

                if ($organizationId) {
                    DB::table('billing_documents')
                        ->where('id', $document->id)
                        ->update(['organization_id' => $organizationId]);
                }
            });
    }

    private function backfillBillingDocumentFiles(): void
    {
        if (! Schema::hasTable('billing_document_files') || ! Schema::hasTable('billing_documents')) {
            return;
        }

        $documentOrganizations = DB::table('billing_documents')->pluck('organization_id', 'id');

        DB::table('billing_document_files')
            ->select('id', 'billing_document_id', 'organization_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $file) use ($documentOrganizations): void {
                if ($file->organization_id) {
                    return;
                }

                $organizationId = $documentOrganizations[$file->billing_document_id] ?? null;

                if ($organizationId) {
                    DB::table('billing_document_files')
                        ->where('id', $file->id)
                        ->update(['organization_id' => $organizationId]);
                }
            });
    }

    private function backfillBillingDocumentResponseHistories(): void
    {
        if (! Schema::hasTable('billing_document_response_histories') || ! Schema::hasTable('billing_documents')) {
            return;
        }

        $documentOrganizations = DB::table('billing_documents')->pluck('organization_id', 'id');

        DB::table('billing_document_response_histories')
            ->select('id', 'billing_document_id', 'organization_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $history) use ($documentOrganizations): void {
                if ($history->organization_id) {
                    return;
                }

                $organizationId = $documentOrganizations[$history->billing_document_id] ?? null;

                if ($organizationId) {
                    DB::table('billing_document_response_histories')
                        ->where('id', $history->id)
                        ->update(['organization_id' => $organizationId]);
                }
            });
    }

    private function backfillInventoryTables(): void
    {
        $branchOrganizations = Schema::hasTable('security_branches')
            ? DB::table('security_branches')->pluck('organization_id', 'id')
            : collect();

        foreach (['product_branch_stocks', 'inventory_movements', 'inventory_warehouses', 'product_warehouse_stocks', 'inventory_documents'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->select('id', 'branch_id', 'organization_id')
                ->orderBy('id')
                ->get()
                ->each(function (object $row) use ($branchOrganizations, $table): void {
                    if ($row->organization_id) {
                        return;
                    }

                    $organizationId = $branchOrganizations[$row->branch_id] ?? null;

                    if ($organizationId) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['organization_id' => $organizationId]);
                    }
                });
        }

        if (Schema::hasTable('inventory_document_items') && Schema::hasTable('inventory_documents')) {
            $documentOrganizations = DB::table('inventory_documents')->pluck('organization_id', 'id');

            DB::table('inventory_document_items')
                ->select('id', 'document_id', 'organization_id')
                ->orderBy('id')
                ->get()
                ->each(function (object $item) use ($documentOrganizations): void {
                    if ($item->organization_id) {
                        return;
                    }

                    $organizationId = $documentOrganizations[$item->document_id] ?? null;

                    if ($organizationId) {
                        DB::table('inventory_document_items')
                            ->where('id', $item->id)
                            ->update(['organization_id' => $organizationId]);
                    }
                });
        }
    }

    private function rebuildSecurityBranchUnique(): void
    {
        if (! Schema::hasTable('security_branches') || ! Schema::hasColumn('security_branches', 'organization_id')) {
            return;
        }

        Schema::table('security_branches', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('security_branches_code_unique');
            $blueprint->unique(['organization_id', 'code'], 'security_branches_organization_code_unique');
        });
    }

    private function rollbackSecurityBranchUnique(): void
    {
        if (! Schema::hasTable('security_branches')) {
            return;
        }

        Schema::table('security_branches', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('security_branches_organization_code_unique');
            $blueprint->unique('code');
        });
    }

    private function rebuildBillingDocumentsUnique(): void
    {
        if (! Schema::hasTable('billing_documents') || ! Schema::hasColumn('billing_documents', 'organization_id')) {
            return;
        }

        Schema::table('billing_documents', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('billing_documents_document_type_series_number_unique');
            $blueprint->unique(['organization_id', 'document_type', 'series', 'number'], 'billing_documents_org_doc_series_number_unique');
        });
    }

    private function rollbackBillingDocumentsUnique(): void
    {
        if (! Schema::hasTable('billing_documents')) {
            return;
        }

        Schema::table('billing_documents', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('billing_documents_org_doc_series_number_unique');
            $blueprint->unique(['document_type', 'series', 'number']);
        });
    }

    private function rebuildInventoryDocumentsUnique(): void
    {
        if (! Schema::hasTable('inventory_documents') || ! Schema::hasColumn('inventory_documents', 'organization_id')) {
            return;
        }

        Schema::table('inventory_documents', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('inventory_documents_code_unique');
            $blueprint->unique(['organization_id', 'code'], 'inventory_documents_organization_code_unique');
        });
    }

    private function rollbackInventoryDocumentsUnique(): void
    {
        if (! Schema::hasTable('inventory_documents')) {
            return;
        }

        Schema::table('inventory_documents', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('inventory_documents_organization_code_unique');
            $blueprint->unique('code');
        });
    }
};
