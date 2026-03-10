<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_settings', 'default_invoice_operation_code')) {
                $table->string('default_invoice_operation_code', 2)->nullable()->after('debit_note_series');
            }

            if (! Schema::hasColumn('billing_settings', 'default_receipt_operation_code')) {
                $table->string('default_receipt_operation_code', 2)->nullable()->after('default_invoice_operation_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            if (Schema::hasColumn('billing_settings', 'default_receipt_operation_code')) {
                $table->dropColumn('default_receipt_operation_code');
            }
            if (Schema::hasColumn('billing_settings', 'default_invoice_operation_code')) {
                $table->dropColumn('default_invoice_operation_code');
            }
        });
    }
};

