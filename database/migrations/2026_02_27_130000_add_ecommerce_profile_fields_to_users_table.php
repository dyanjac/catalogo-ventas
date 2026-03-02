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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('document_type', 20)->nullable()->after('phone');
            $table->string('document_number', 30)->nullable()->after('document_type');
            $table->string('city', 100)->nullable()->after('document_number');
            $table->string('address', 200)->nullable()->after('city');
            $table->boolean('is_active')->default(true)->after('address');

            $table->index('document_number');
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_document_number_index');
            $table->dropIndex('users_phone_index');
            $table->dropColumn([
                'phone',
                'document_type',
                'document_number',
                'city',
                'address',
                'is_active',
            ]);
        });
    }
};
