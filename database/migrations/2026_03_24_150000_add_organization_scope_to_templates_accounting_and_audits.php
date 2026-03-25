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

        $this->addOrganizationIdColumn('document_templates');
        $this->addOrganizationIdColumn('accounting_settings');
        $this->addOrganizationIdColumn('security_audit_logs');
        $this->addOrganizationIdColumn('accounting_audit_logs');

        $this->backfillDocumentTemplates($defaultOrganizationId);
        $this->backfillAccountingSettings($defaultOrganizationId);
        $this->backfillSecurityAuditLogs($defaultOrganizationId);
        $this->backfillAccountingAuditLogs($defaultOrganizationId);

        $this->rebuildDocumentTemplateIndexes();
        $this->rebuildAccountingSettingsIndexes();
        $this->dropDocumentTemplateCompanyColumn();
    }

    public function down(): void
    {
        $this->restoreDocumentTemplateCompanyColumn();
        $this->rollbackAccountingSettingsIndexes();
        $this->rollbackDocumentTemplateIndexes();

        $this->dropOrganizationIdColumn('accounting_audit_logs');
        $this->dropOrganizationIdColumn('security_audit_logs');
        $this->dropOrganizationIdColumn('accounting_settings');
        $this->dropOrganizationIdColumn('document_templates');
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

    private function backfillDocumentTemplates(int $defaultOrganizationId): void
    {
        if (! Schema::hasTable('document_templates') || ! Schema::hasColumn('document_templates', 'organization_id')) {
            return;
        }

        DB::table('document_templates')
            ->select('id', 'company_id', 'organization_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $template) use ($defaultOrganizationId): void {
                if ($template->organization_id) {
                    return;
                }

                $organizationId = $template->company_id ?: ($defaultOrganizationId ?: null);

                if ($organizationId) {
                    DB::table('document_templates')
                        ->where('id', $template->id)
                        ->update(['organization_id' => $organizationId]);
                }
            });
    }

    private function backfillAccountingSettings(int $defaultOrganizationId): void
    {
        if (! Schema::hasTable('accounting_settings') || ! Schema::hasColumn('accounting_settings', 'organization_id') || ! $defaultOrganizationId) {
            return;
        }

        DB::table('accounting_settings')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
    }

    private function backfillSecurityAuditLogs(int $defaultOrganizationId): void
    {
        if (! Schema::hasTable('security_audit_logs') || ! Schema::hasColumn('security_audit_logs', 'organization_id')) {
            return;
        }

        $userOrganizations = Schema::hasTable('users')
            ? DB::table('users')->pluck('organization_id', 'id')
            : collect();

        DB::table('security_audit_logs')
            ->select('id', 'actor_user_id', 'target_user_id', 'organization_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $log) use ($userOrganizations, $defaultOrganizationId): void {
                if ($log->organization_id) {
                    return;
                }

                $organizationId = $userOrganizations[$log->actor_user_id] ?? $userOrganizations[$log->target_user_id] ?? null;
                $organizationId = $organizationId ?: ($defaultOrganizationId ?: null);

                if ($organizationId) {
                    DB::table('security_audit_logs')->where('id', $log->id)->update(['organization_id' => $organizationId]);
                }
            });
    }

    private function backfillAccountingAuditLogs(int $defaultOrganizationId): void
    {
        if (! Schema::hasTable('accounting_audit_logs') || ! Schema::hasColumn('accounting_audit_logs', 'organization_id')) {
            return;
        }

        $userOrganizations = Schema::hasTable('users')
            ? DB::table('users')->pluck('organization_id', 'id')
            : collect();

        DB::table('accounting_audit_logs')
            ->select('id', 'user_id', 'organization_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $log) use ($userOrganizations, $defaultOrganizationId): void {
                if ($log->organization_id) {
                    return;
                }

                $organizationId = $userOrganizations[$log->user_id] ?? null;
                $organizationId = $organizationId ?: ($defaultOrganizationId ?: null);

                if ($organizationId) {
                    DB::table('accounting_audit_logs')->where('id', $log->id)->update(['organization_id' => $organizationId]);
                }
            });
    }

    private function rebuildDocumentTemplateIndexes(): void
    {
        if (! Schema::hasTable('document_templates') || ! Schema::hasColumn('document_templates', 'organization_id')) {
            return;
        }

        Schema::table('document_templates', function (Blueprint $blueprint): void {
            $blueprint->dropIndex('document_templates_company_type_active_idx');
            $blueprint->index(['organization_id', 'document_type', 'is_active'], 'document_templates_org_type_active_idx');
        });
    }

    private function rollbackDocumentTemplateIndexes(): void
    {
        if (! Schema::hasTable('document_templates') || ! Schema::hasColumn('document_templates', 'organization_id')) {
            return;
        }

        Schema::table('document_templates', function (Blueprint $blueprint): void {
            $blueprint->dropIndex('document_templates_org_type_active_idx');
            $blueprint->index(['company_id', 'document_type', 'is_active'], 'document_templates_company_type_active_idx');
        });
    }

    private function rebuildAccountingSettingsIndexes(): void
    {
        if (! Schema::hasTable('accounting_settings') || ! Schema::hasColumn('accounting_settings', 'organization_id')) {
            return;
        }

        Schema::table('accounting_settings', function (Blueprint $blueprint): void {
            $blueprint->unique(['organization_id'], 'accounting_settings_organization_unique');
        });
    }

    private function rollbackAccountingSettingsIndexes(): void
    {
        if (! Schema::hasTable('accounting_settings') || ! Schema::hasColumn('accounting_settings', 'organization_id')) {
            return;
        }

        Schema::table('accounting_settings', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('accounting_settings_organization_unique');
        });
    }

    private function dropDocumentTemplateCompanyColumn(): void
    {
        if (! Schema::hasTable('document_templates') || ! Schema::hasColumn('document_templates', 'company_id')) {
            return;
        }

        Schema::table('document_templates', function (Blueprint $blueprint): void {
            $blueprint->dropIndex('document_templates_company_id_index');
            $blueprint->dropColumn('company_id');
        });
    }

    private function restoreDocumentTemplateCompanyColumn(): void
    {
        if (! Schema::hasTable('document_templates') || Schema::hasColumn('document_templates', 'company_id')) {
            return;
        }

        Schema::table('document_templates', function (Blueprint $blueprint): void {
            $blueprint->unsignedBigInteger('company_id')->nullable()->after('organization_id');
            $blueprint->index(['company_id'], 'document_templates_company_id_index');
        });
    }
};
