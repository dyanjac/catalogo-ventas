<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_auth_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('security_auth_settings', 'ldap_fallback_email_domain')) {
                $table->string('ldap_fallback_email_domain', 160)->default('ldap.local')->after('ldap_admin_group_names');
            }
        });
    }

    public function down(): void
    {
        Schema::table('security_auth_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('security_auth_settings', 'ldap_fallback_email_domain')) {
                $table->dropColumn('ldap_fallback_email_domain');
            }
        });
    }
};
