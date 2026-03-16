<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_auth_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('security_auth_settings', 'ldap_email_attribute')) {
                $table->string('ldap_email_attribute', 120)->default('mail')->after('ldap_username_attribute');
            }
        });
    }

    public function down(): void
    {
        Schema::table('security_auth_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('security_auth_settings', 'ldap_email_attribute')) {
                $table->dropColumn('ldap_email_attribute');
            }
        });
    }
};
