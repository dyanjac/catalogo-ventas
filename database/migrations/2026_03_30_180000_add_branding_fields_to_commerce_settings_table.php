<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_settings', 'brand_name')) {
                $table->string('brand_name', 160)->nullable()->after('organization_id');
            }

            if (! Schema::hasColumn('commerce_settings', 'tagline')) {
                $table->string('tagline', 255)->nullable()->after('company_name');
            }

            if (! Schema::hasColumn('commerce_settings', 'support_email')) {
                $table->string('support_email', 255)->nullable()->after('email');
            }

            if (! Schema::hasColumn('commerce_settings', 'support_phone')) {
                $table->string('support_phone', 30)->nullable()->after('mobile');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commerce_settings', function (Blueprint $table) {
            foreach (['brand_name', 'tagline', 'support_email', 'support_phone'] as $column) {
                if (Schema::hasColumn('commerce_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
