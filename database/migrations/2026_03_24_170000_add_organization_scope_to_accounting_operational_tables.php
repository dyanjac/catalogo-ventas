<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultOrganizationId = (int) (DB::table('organizations')->where('is_default', true)->value('id') ?? 0);

        foreach (['accounting_accounts', 'accounting_periods', 'accounting_cost_centers', 'accounting_entries', 'accounting_entry_lines', 'accounting_entry_attachments'] as $table) {
            $this->addOrganizationIdColumn($table);
        }

        $this->backfillAccountingAccounts($defaultOrganizationId);
        $this->backfillAccountingPeriods($defaultOrganizationId);
        $this->backfillAccountingCostCenters($defaultOrganizationId);
        $this->backfillAccountingEntries($defaultOrganizationId);
        $this->backfillAccountingEntryLines($defaultOrganizationId);
        $this->backfillAccountingEntryAttachments($defaultOrganizationId);

        $this->rebuildAccountingAccountsIndexes();
        $this->rebuildAccountingPeriodsIndexes();
        $this->rebuildAccountingCostCentersIndexes();
    }

    public function down(): void
    {
        $this->rollbackAccountingCostCentersIndexes();
        $this->rollbackAccountingPeriodsIndexes();
        $this->rollbackAccountingAccountsIndexes();

        foreach (['accounting_entry_attachments', 'accounting_entry_lines', 'accounting_entries', 'accounting_cost_centers', 'accounting_periods', 'accounting_accounts'] as $table) {
            $this->dropOrganizationIdColumn($table);
        }
    }

    private function addOrganizationIdColumn(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'organization_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $blueprint->foreignId('organization_id')
                ->nullable()
                ->after('id')
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

    private function backfillAccountingAccounts(int $defaultOrganizationId): void
    {
        if (! Schema::hasTable('accounting_accounts') || ! Schema::hasColumn('accounting_accounts', 'organization_id') || ! $defaultOrganizationId) {
            return;
        }

        DB::table('accounting_accounts')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
    }

    private function backfillAccountingPeriods(int $defaultOrganizationId): void
    {
        if (! Schema::hasTable('accounting_periods') || ! Schema::hasColumn('accounting_periods', 'organization_id') || ! $defaultOrganizationId) {
            return;
        }

        DB::table('accounting_periods')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
    }

    private function backfillAccountingCostCenters(int $defaultOrganizationId): void
    {
        if (! Schema::hasTable('accounting_cost_centers') || ! Schema::hasColumn('accounting_cost_centers', 'organization_id') || ! $defaultOrganizationId) {
            return;
        }

        DB::table('accounting_cost_centers')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
    }

    private function backfillAccountingEntries(int $defaultOrganizationId): void
    {
        if (! Schema::hasTable('accounting_entries') || ! Schema::hasColumn('accounting_entries', 'organization_id')) {
            return;
        }

        $userOrganizations = Schema::hasTable('users')
            ? DB::table('users')->pluck('organization_id', 'id')
            : collect();

        DB::table('accounting_entries')
            ->select('id', 'created_by', 'organization_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $entry) use ($userOrganizations, $defaultOrganizationId): void {
                if ($entry->organization_id) {
                    return;
                }

                $organizationId = $userOrganizations[$entry->created_by] ?? null;
                $organizationId = $organizationId ?: ($defaultOrganizationId ?: null);

                if ($organizationId) {
                    DB::table('accounting_entries')->where('id', $entry->id)->update(['organization_id' => $organizationId]);
                }
            });
    }

    private function backfillAccountingEntryLines(int $defaultOrganizationId): void
    {
        if (! Schema::hasTable('accounting_entry_lines') || ! Schema::hasColumn('accounting_entry_lines', 'organization_id')) {
            return;
        }

        $entryOrganizations = Schema::hasTable('accounting_entries')
            ? DB::table('accounting_entries')->pluck('organization_id', 'id')
            : collect();

        DB::table('accounting_entry_lines')
            ->select('id', 'accounting_entry_id', 'organization_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $line) use ($entryOrganizations, $defaultOrganizationId): void {
                if ($line->organization_id) {
                    return;
                }

                $organizationId = $entryOrganizations[$line->accounting_entry_id] ?? null;
                $organizationId = $organizationId ?: ($defaultOrganizationId ?: null);

                if ($organizationId) {
                    DB::table('accounting_entry_lines')->where('id', $line->id)->update(['organization_id' => $organizationId]);
                }
            });
    }

    private function backfillAccountingEntryAttachments(int $defaultOrganizationId): void
    {
        if (! Schema::hasTable('accounting_entry_attachments') || ! Schema::hasColumn('accounting_entry_attachments', 'organization_id')) {
            return;
        }

        $entryOrganizations = Schema::hasTable('accounting_entries')
            ? DB::table('accounting_entries')->pluck('organization_id', 'id')
            : collect();

        DB::table('accounting_entry_attachments')
            ->select('id', 'accounting_entry_id', 'organization_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $attachment) use ($entryOrganizations, $defaultOrganizationId): void {
                if ($attachment->organization_id) {
                    return;
                }

                $organizationId = $entryOrganizations[$attachment->accounting_entry_id] ?? null;
                $organizationId = $organizationId ?: ($defaultOrganizationId ?: null);

                if ($organizationId) {
                    DB::table('accounting_entry_attachments')->where('id', $attachment->id)->update(['organization_id' => $organizationId]);
                }
            });
    }

    private function rebuildAccountingAccountsIndexes(): void
    {
        if (! Schema::hasTable('accounting_accounts') || ! Schema::hasColumn('accounting_accounts', 'organization_id')) {
            return;
        }

        Schema::table('accounting_accounts', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('accounting_accounts_code_unique');
            $blueprint->unique(['organization_id', 'code'], 'accounting_accounts_org_code_unique');
        });
    }

    private function rollbackAccountingAccountsIndexes(): void
    {
        if (! Schema::hasTable('accounting_accounts') || ! Schema::hasColumn('accounting_accounts', 'organization_id')) {
            return;
        }

        Schema::table('accounting_accounts', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('accounting_accounts_org_code_unique');
            $blueprint->unique('code');
        });
    }

    private function rebuildAccountingPeriodsIndexes(): void
    {
        if (! Schema::hasTable('accounting_periods') || ! Schema::hasColumn('accounting_periods', 'organization_id')) {
            return;
        }

        Schema::table('accounting_periods', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('accounting_periods_year_month_unique');
            $blueprint->unique(['organization_id', 'year', 'month'], 'accounting_periods_org_year_month_unique');
        });
    }

    private function rollbackAccountingPeriodsIndexes(): void
    {
        if (! Schema::hasTable('accounting_periods') || ! Schema::hasColumn('accounting_periods', 'organization_id')) {
            return;
        }

        Schema::table('accounting_periods', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('accounting_periods_org_year_month_unique');
            $blueprint->unique(['year', 'month']);
        });
    }

    private function rebuildAccountingCostCentersIndexes(): void
    {
        if (! Schema::hasTable('accounting_cost_centers') || ! Schema::hasColumn('accounting_cost_centers', 'organization_id')) {
            return;
        }

        Schema::table('accounting_cost_centers', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('accounting_cost_centers_code_unique');
            $blueprint->unique(['organization_id', 'code'], 'accounting_cost_centers_org_code_unique');
        });
    }

    private function rollbackAccountingCostCentersIndexes(): void
    {
        if (! Schema::hasTable('accounting_cost_centers') || ! Schema::hasColumn('accounting_cost_centers', 'organization_id')) {
            return;
        }

        Schema::table('accounting_cost_centers', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('accounting_cost_centers_org_code_unique');
            $blueprint->unique('code');
        });
    }
};
