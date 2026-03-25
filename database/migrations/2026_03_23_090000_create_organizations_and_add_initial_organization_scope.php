<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('tax_id', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('environment', 20)->default('production');
            $table->boolean('is_default')->default(false);
            $table->json('settings_json')->nullable();
            $table->timestamps();
        });

        $this->addOrganizationIdColumn('users', 'email');
        $this->addOrganizationIdColumn('security_branches', 'code');
        $this->addOrganizationIdColumn('orders', 'branch_id');
        $this->addOrganizationIdColumn('commerce_settings', 'id');
        $this->addOrganizationIdColumn('billing_settings', 'id');

        $defaultOrganizationId = $this->ensureDefaultOrganization();

        DB::table('users')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
        DB::table('security_branches')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
        DB::table('commerce_settings')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);
        DB::table('billing_settings')->whereNull('organization_id')->update(['organization_id' => $defaultOrganizationId]);

        $userOrganizations = DB::table('users')->pluck('organization_id', 'id');

        DB::table('orders')
            ->select('id', 'user_id', 'organization_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $order) use ($userOrganizations, $defaultOrganizationId): void {
                if ($order->organization_id) {
                    return;
                }

                $organizationId = (int) ($userOrganizations[$order->user_id] ?? $defaultOrganizationId);

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['organization_id' => $organizationId]);
            });
    }

    public function down(): void
    {
        $this->dropOrganizationIdColumn('billing_settings');
        $this->dropOrganizationIdColumn('commerce_settings');
        $this->dropOrganizationIdColumn('orders');
        $this->dropOrganizationIdColumn('security_branches');
        $this->dropOrganizationIdColumn('users');

        Schema::dropIfExists('organizations');
    }

    private function addOrganizationIdColumn(string $table, string $after): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'organization_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($after, $table): void {
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

    private function ensureDefaultOrganization(): int
    {
        $defaultOrganizationId = DB::table('organizations')->where('is_default', true)->value('id');

        if ($defaultOrganizationId) {
            return (int) $defaultOrganizationId;
        }

        $commerceName = DB::table('commerce_settings')->value('company_name')
            ?: config('commerce.name', 'Organizacion principal');
        $taxId = DB::table('commerce_settings')->value('tax_id');
        $name = trim((string) $commerceName) !== '' ? trim((string) $commerceName) : 'Organizacion principal';
        $slug = Str::slug($name);

        if ($slug === '') {
            $slug = 'organizacion-principal';
        }

        $existing = DB::table('organizations')->where('slug', $slug)->value('id');

        if ($existing) {
            DB::table('organizations')->where('id', $existing)->update([
                'is_default' => true,
                'status' => 'active',
                'environment' => app()->environment(['local', 'development', 'testing']) ? 'demo' : 'production',
            ]);

            return (int) $existing;
        }

        return (int) DB::table('organizations')->insertGetId([
            'code' => 'DEFAULT',
            'name' => $name,
            'slug' => $slug,
            'tax_id' => $taxId,
            'status' => 'active',
            'environment' => app()->environment(['local', 'development', 'testing']) ? 'demo' : 'production',
            'is_default' => true,
            'settings_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
