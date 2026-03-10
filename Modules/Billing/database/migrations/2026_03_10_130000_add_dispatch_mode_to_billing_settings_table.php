<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_settings', 'dispatch_mode')) {
                $table->string('dispatch_mode', 20)->default('sync')->after('environment');
            }

            if (! Schema::hasColumn('billing_settings', 'queue_connection')) {
                $table->string('queue_connection', 40)->nullable()->after('dispatch_mode');
            }

            if (! Schema::hasColumn('billing_settings', 'queue_name')) {
                $table->string('queue_name', 80)->nullable()->after('queue_connection');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            if (Schema::hasColumn('billing_settings', 'queue_name')) {
                $table->dropColumn('queue_name');
            }
            if (Schema::hasColumn('billing_settings', 'queue_connection')) {
                $table->dropColumn('queue_connection');
            }
            if (Schema::hasColumn('billing_settings', 'dispatch_mode')) {
                $table->dropColumn('dispatch_mode');
            }
        });
    }
};
