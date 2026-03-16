<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_user_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider_type', 24);
            $table->string('provider_key', 80);
            $table->string('provider_identifier', 191);
            $table->string('provider_email')->nullable();
            $table->string('provider_dn')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_type', 'provider_identifier'], 'security_identity_provider_identifier_unique');
            $table->unique(['provider_type', 'provider_key', 'user_id'], 'security_identity_provider_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_user_identities');
    }
};
