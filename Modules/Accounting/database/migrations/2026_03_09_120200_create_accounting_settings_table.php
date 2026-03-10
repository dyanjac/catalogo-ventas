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
        Schema::create('accounting_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->string('default_currency', 3)->default('PEN');
            $table->boolean('period_closure_enabled')->default(false);
            $table->boolean('auto_post_entries')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_settings');
    }
};
