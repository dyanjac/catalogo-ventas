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
        if (! Schema::hasColumn('products', 'requires_accounting_entry')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('requires_accounting_entry')->default(true)->after('account');
            });
        }

        if (! Schema::hasColumn('products', 'account_revenue')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('account_revenue', 120)->nullable()->after('requires_accounting_entry');
            });
        }

        if (! Schema::hasColumn('products', 'account_receivable')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('account_receivable', 120)->nullable()->after('account_revenue');
            });
        }

        if (! Schema::hasColumn('products', 'account_inventory')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('account_inventory', 120)->nullable()->after('account_receivable');
            });
        }

        if (! Schema::hasColumn('products', 'account_cogs')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('account_cogs', 120)->nullable()->after('account_inventory');
            });
        }

        if (! Schema::hasColumn('products', 'account_tax')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('account_tax', 120)->nullable()->after('account_cogs');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = [
            'requires_accounting_entry',
            'account_revenue',
            'account_receivable',
            'account_inventory',
            'account_cogs',
            'account_tax',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('products', $column)) {
                Schema::table('products', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
