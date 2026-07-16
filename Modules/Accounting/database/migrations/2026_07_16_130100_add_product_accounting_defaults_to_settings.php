<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_settings', function (Blueprint $table): void {
            $table->string('product_accounting_treatment', 40)
                ->default('PENDIENTE_CONFIGURACION')
                ->after('auto_post_entries');
            $table->string('default_account_revenue', 120)->nullable()->after('product_accounting_treatment');
            $table->string('default_account_receivable', 120)->nullable()->after('default_account_revenue');
            $table->string('default_account_inventory', 120)->nullable()->after('default_account_receivable');
            $table->string('default_account_cogs', 120)->nullable()->after('default_account_inventory');
            $table->string('default_account_tax', 120)->nullable()->after('default_account_cogs');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'product_accounting_treatment',
                'default_account_revenue',
                'default_account_receivable',
                'default_account_inventory',
                'default_account_cogs',
                'default_account_tax',
            ]);
        });
    }
};
