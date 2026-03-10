<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('accounting_entry_lines', 'cost_center_id')) {
            Schema::table('accounting_entry_lines', function (Blueprint $table) {
                $table->foreignId('cost_center_id')->nullable()->after('line_description')
                    ->constrained('accounting_cost_centers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounting_entry_lines', 'cost_center_id')) {
            Schema::table('accounting_entry_lines', function (Blueprint $table) {
                $table->dropConstrainedForeignId('cost_center_id');
            });
        }
    }
};
