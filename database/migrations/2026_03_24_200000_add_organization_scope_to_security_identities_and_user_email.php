<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('security_user_identities')) {
            Schema::table('security_user_identities', function (Blueprint $table): void {
                if (! Schema::hasColumn('security_user_identities', 'organization_id')) {
                    $table->foreignId('organization_id')->nullable()->after('user_id')->constrained('organizations')->nullOnDelete();
                }
            });

            DB::table('security_user_identities as identities')
                ->join('users', 'users.id', '=', 'identities.user_id')
                ->whereNull('identities.organization_id')
                ->update([
                    'identities.organization_id' => DB::raw('users.organization_id'),
                ]);

            Schema::table('security_user_identities', function (Blueprint $table): void {
                $table->dropUnique('security_identity_provider_identifier_unique');
                $table->unique(['organization_id', 'provider_type', 'provider_identifier'], 'security_identity_org_provider_identifier_unique');
                $table->index(['organization_id', 'user_id'], 'security_identity_org_user_idx');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'organization_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_email_unique');
                $table->unique(['organization_id', 'email'], 'users_organization_email_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'organization_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_organization_email_unique');
                $table->unique('email');
            });
        }

        if (Schema::hasTable('security_user_identities')) {
            Schema::table('security_user_identities', function (Blueprint $table): void {
                $table->dropIndex('security_identity_org_user_idx');
                $table->dropUnique('security_identity_org_provider_identifier_unique');
                $table->unique(['provider_type', 'provider_identifier'], 'security_identity_provider_identifier_unique');

                if (Schema::hasColumn('security_user_identities', 'organization_id')) {
                    $table->dropConstrainedForeignId('organization_id');
                }
            });
        }
    }
};
