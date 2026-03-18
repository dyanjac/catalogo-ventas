<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('implemented');
            $table->boolean('navigation_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('security_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('security_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id')->nullable()->constrained('security_modules')->nullOnDelete();
            $table->string('resource', 120);
            $table->string('action', 60);
            $table->string('code', 180)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('security_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('security_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('security_permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('security_user_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('security_roles')->cascadeOnDelete();
            $table->string('scope', 32)->default('all');
            $table->boolean('is_active')->default(true);
            $table->json('context')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('security_role_module_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('security_roles')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('security_modules')->cascadeOnDelete();
            $table->string('access_level', 32)->default('none');
            $table->boolean('navigation_visible')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_role_module_access');
        Schema::dropIfExists('security_user_roles');
        Schema::dropIfExists('security_role_permissions');
        Schema::dropIfExists('security_permissions');
        Schema::dropIfExists('security_roles');
        Schema::dropIfExists('security_modules');
    }
};
