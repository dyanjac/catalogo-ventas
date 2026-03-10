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
        Schema::create('admin_theme_settings', function (Blueprint $table) {
            $table->id();
            $table->string('sidebar_bg', 20)->nullable();
            $table->string('sidebar_gradient_to', 20)->nullable();
            $table->string('sidebar_text', 20)->nullable();
            $table->string('topbar_bg', 20)->nullable();
            $table->string('topbar_text', 20)->nullable();
            $table->string('primary_button', 20)->nullable();
            $table->string('primary_button_hover', 20)->nullable();
            $table->string('active_link_bg', 20)->nullable();
            $table->string('active_link_text', 20)->nullable();
            $table->string('card_border', 20)->nullable();
            $table->string('focus_ring', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_theme_settings');
    }
};
