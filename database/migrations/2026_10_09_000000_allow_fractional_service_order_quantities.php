<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores fractional numeric values in INTEGER-affinity columns without a table rebuild.
        // Rebuilding order_items would invalidate cross-table tenant triggers during test migrations.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('quantity', 12, 3)->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->change();
        });
    }
};
