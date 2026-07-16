<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('product_type', 40)->default('bien_fisico')->after('tax_affectation');
            $table->string('accounting_treatment', 40)->default('HEREDAR')->after('requires_accounting_entry');
            $table->index(['organization_id', 'product_type'], 'products_org_product_type_idx');
            $table->index(['organization_id', 'accounting_treatment'], 'products_org_accounting_treatment_idx');
        });

        DB::table('products')
            ->where('requires_accounting_entry', true)
            ->update(['accounting_treatment' => 'AUTOMATICO']);

        DB::table('products')
            ->where('requires_accounting_entry', false)
            ->update(['accounting_treatment' => 'NO_APLICA']);

        Schema::table('categories', function (Blueprint $table): void {
            $table->string('accounting_treatment', 40)->default('HEREDAR')->after('description');
            $table->string('account_revenue', 120)->nullable()->after('accounting_treatment');
            $table->string('account_receivable', 120)->nullable()->after('account_revenue');
            $table->string('account_inventory', 120)->nullable()->after('account_receivable');
            $table->string('account_cogs', 120)->nullable()->after('account_inventory');
            $table->string('account_tax', 120)->nullable()->after('account_cogs');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn([
                'accounting_treatment',
                'account_revenue',
                'account_receivable',
                'account_inventory',
                'account_cogs',
                'account_tax',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_org_product_type_idx');
            $table->dropIndex('products_org_accounting_treatment_idx');
            $table->dropColumn(['product_type', 'accounting_treatment']);
        });
    }
};
