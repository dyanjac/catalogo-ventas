<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_auth_settings', function (Blueprint $table): void {
            $table->text('ldap_group_role_map')->nullable()->after('ldap_admin_group_names');
        });
    }

    public function down(): void
    {
        Schema::table('security_auth_settings', function (Blueprint $table): void {
            $table->dropColumn('ldap_group_role_map');
        });
    }
};
