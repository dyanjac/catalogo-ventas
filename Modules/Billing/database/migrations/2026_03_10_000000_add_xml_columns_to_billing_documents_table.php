<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_documents', 'xml_path')) {
                $table->string('xml_path')->nullable()->after('response_payload');
            }

            if (! Schema::hasColumn('billing_documents', 'xml_hash')) {
                $table->string('xml_hash', 64)->nullable()->after('xml_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_documents', function (Blueprint $table) {
            if (Schema::hasColumn('billing_documents', 'xml_hash')) {
                $table->dropColumn('xml_hash');
            }

            if (Schema::hasColumn('billing_documents', 'xml_path')) {
                $table->dropColumn('xml_path');
            }
        });
    }
};
