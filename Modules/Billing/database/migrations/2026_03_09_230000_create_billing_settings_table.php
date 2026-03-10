<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('country', 2)->default('PE');
            $table->string('provider', 30)->default('greenter');
            $table->string('environment', 20)->default('sandbox');
            $table->json('provider_credentials')->nullable();
            $table->string('invoice_series', 10)->nullable();
            $table->string('receipt_series', 10)->nullable();
            $table->string('credit_note_series', 10)->nullable();
            $table->string('debit_note_series', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
