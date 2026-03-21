<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_theme_settings', function (Blueprint $table) {
            $table->string('sidebar_group_bg', 20)->nullable()->after('sidebar_group_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_theme_settings', function (Blueprint $table) {
            $table->dropColumn('sidebar_group_bg');
        });
    }
};
