<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('security_branches')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'code'], 'inventory_warehouses_branch_code_unique');
            $table->index(['branch_id', 'is_active'], 'inventory_warehouses_branch_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_warehouses');
    }
};
