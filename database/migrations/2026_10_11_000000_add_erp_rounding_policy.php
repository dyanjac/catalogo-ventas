<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_settings', function (Blueprint $table): void {
            $table->string('rounding_mode', 16)->default('half_up');
        });

        Schema::table('security_branches', function (Blueprint $table): void {
            $table->string('rounding_mode', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('security_branches', fn (Blueprint $table) => $table->dropColumn('rounding_mode'));
        Schema::table('commerce_settings', fn (Blueprint $table) => $table->dropColumn('rounding_mode'));
    }
};
