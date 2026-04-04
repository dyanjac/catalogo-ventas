<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_theme_settings', function (Blueprint $table) {
            $table->string('user_menu_trigger_bg', 20)->nullable()->after('topbar_text');
            $table->string('user_menu_trigger_text', 20)->nullable()->after('user_menu_trigger_bg');
            $table->string('user_menu_dropdown_bg', 20)->nullable()->after('user_menu_trigger_text');
            $table->string('user_menu_dropdown_text', 20)->nullable()->after('user_menu_dropdown_bg');
            $table->string('user_menu_dropdown_hover_bg', 20)->nullable()->after('user_menu_dropdown_text');
            $table->string('user_menu_dropdown_hover_text', 20)->nullable()->after('user_menu_dropdown_hover_bg');
        });
    }

    public function down(): void
    {
        Schema::table('admin_theme_settings', function (Blueprint $table) {
            $table->dropColumn([
                'user_menu_trigger_bg',
                'user_menu_trigger_text',
                'user_menu_dropdown_bg',
                'user_menu_dropdown_text',
                'user_menu_dropdown_hover_bg',
                'user_menu_dropdown_hover_text',
            ]);
        });
    }
};