<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('accounting_accounts', 'is_default_receivable')) {
            Schema::table('accounting_accounts', function (Blueprint $table) {
                $table->boolean('is_default_receivable')->default(false)->after('is_default_tax');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounting_accounts', 'is_default_receivable')) {
            Schema::table('accounting_accounts', function (Blueprint $table) {
                $table->dropColumn('is_default_receivable');
            });
        }
    }
};
