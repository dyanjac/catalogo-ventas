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
        if (! Schema::hasColumn('products', 'unit_measure_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('unit_measure_id')->nullable()->after('category_id');
            });
        }

        if (! Schema::hasColumn('products', 'sku')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('sku')->nullable()->unique()->after('name');
            });
        }

        if (! Schema::hasColumn('products', 'tax_affectation')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('tax_affectation')->default('Gravado')->after('description');
            });
        }

        if (! Schema::hasColumn('products', 'uses_series')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('uses_series')->default(false)->after('tax_affectation');
            });
        }

        if (! Schema::hasColumn('products', 'account')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('account')->nullable()->after('uses_series');
            });
        }

        if (! Schema::hasColumn('products', 'purchase_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('purchase_price', 12, 2)->nullable()->after('account');
            });
        }

        if (! Schema::hasColumn('products', 'sale_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('sale_price', 12, 2)->nullable()->after('purchase_price');
            });
        }

        if (! Schema::hasColumn('products', 'wholesale_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('wholesale_price', 12, 2)->nullable()->after('sale_price');
            });
        }

        if (! Schema::hasColumn('products', 'average_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('average_price', 12, 2)->nullable()->after('wholesale_price');
            });
        }

        if (! Schema::hasColumn('products', 'min_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('min_stock')->default(0)->after('stock');
            });
        }

        if (! Schema::hasColumn('products', 'deleted_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        $columns = [
            'unit_measure_id',
            'sku',
            'tax_affectation',
            'uses_series',
            'account',
            'purchase_price',
            'sale_price',
            'wholesale_price',
            'average_price',
            'min_stock',
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
