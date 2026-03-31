<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_auth_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('security_auth_settings', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations');
            }
        });

        $defaultOrganizationId = DB::table('organizations')
            ->where('is_default', true)
            ->value('id')
            ?? DB::table('organizations')->orderBy('id')->value('id');

        if ($defaultOrganizationId) {
            DB::table('security_auth_settings')
                ->whereNull('organization_id')
                ->update(['organization_id' => $defaultOrganizationId]);
        }

        Schema::table('security_auth_settings', function (Blueprint $table) {
            $table->unique('organization_id', 'security_auth_settings_organization_unique');
        });
    }

    public function down(): void
    {
        Schema::table('security_auth_settings', function (Blueprint $table) {
            $table->dropUnique('security_auth_settings_organization_unique');

            if (Schema::hasColumn('security_auth_settings', 'organization_id')) {
                $table->dropConstrainedForeignId('organization_id');
            }
        });
    }
};
