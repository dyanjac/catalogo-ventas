<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addOrganizationIdColumn('categories', 'id');
        $this->addOrganizationIdColumn('products', 'category_id');
        $this->addOrganizationIdColumn('unit_measures', 'id');
        $this->addOrganizationIdColumn('product_images', 'product_id');

        $defaultOrganizationId = (int) (DB::table('organizations')->where('is_default', true)->value('id') ?? 0);

        if ($defaultOrganizationId > 0) {
            DB::table('categories')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
            DB::table('products')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
            DB::table('unit_measures')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
        }

        $productOrganizations = DB::table('products')->pluck('organization_id', 'id');

        DB::table('product_images')
            ->select('id', 'product_id', 'organization_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $image) use ($productOrganizations, $defaultOrganizationId): void {
                if ($image->organization_id) {
                    return;
                }

                $organizationId = $productOrganizations[$image->product_id] ?? $defaultOrganizationId;

                if ($organizationId) {
                    DB::table('product_images')
                        ->where('id', $image->id)
                        ->update(['organization_id' => $organizationId]);
                }
            });

        $this->rebuildCategoriesUnique();
        $this->rebuildProductsUnique();
        $this->rebuildUnitMeasuresUnique();
    }

    public function down(): void
    {
        $this->rollbackUnitMeasuresUnique();
        $this->rollbackProductsUnique();
        $this->rollbackCategoriesUnique();

        $this->dropOrganizationIdColumn('product_images');
        $this->dropOrganizationIdColumn('unit_measures');
        $this->dropOrganizationIdColumn('products');
        $this->dropOrganizationIdColumn('categories');
    }

    private function addOrganizationIdColumn(string $table, string $after): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'organization_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $after): void {
            $blueprint->foreignId('organization_id')
                ->nullable()
                ->after($after)
                ->constrained('organizations')
                ->nullOnDelete();
            $blueprint->index(['organization_id'], $table.'_organization_id_idx');
        });
    }

    private function dropOrganizationIdColumn(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $blueprint->dropIndex($table.'_organization_id_idx');
            $blueprint->dropConstrainedForeignId('organization_id');
        });
    }

    private function rebuildCategoriesUnique(): void
    {
        Schema::table('categories', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('categories_name_unique');
            $blueprint->dropUnique('categories_slug_unique');
            $blueprint->unique(['organization_id', 'name'], 'categories_organization_name_unique');
            $blueprint->unique(['organization_id', 'slug'], 'categories_organization_slug_unique');
        });
    }

    private function rollbackCategoriesUnique(): void
    {
        Schema::table('categories', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('categories_organization_name_unique');
            $blueprint->dropUnique('categories_organization_slug_unique');
            $blueprint->unique('name');
            $blueprint->unique('slug');
        });
    }

    private function rebuildProductsUnique(): void
    {
        Schema::table('products', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('products_slug_unique');
            $blueprint->unique(['organization_id', 'slug'], 'products_organization_slug_unique');
            $blueprint->unique(['organization_id', 'sku'], 'products_organization_sku_unique');
        });
    }

    private function rollbackProductsUnique(): void
    {
        Schema::table('products', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('products_organization_sku_unique');
            $blueprint->dropUnique('products_organization_slug_unique');
            $blueprint->unique('slug');
        });
    }

    private function rebuildUnitMeasuresUnique(): void
    {
        Schema::table('unit_measures', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('unit_measures_name_unique');
            $blueprint->unique(['organization_id', 'name'], 'unit_measures_organization_name_unique');
        });
    }

    private function rollbackUnitMeasuresUnique(): void
    {
        Schema::table('unit_measures', function (Blueprint $blueprint): void {
            $blueprint->dropUnique('unit_measures_organization_name_unique');
            $blueprint->unique('name');
        });
    }
};
