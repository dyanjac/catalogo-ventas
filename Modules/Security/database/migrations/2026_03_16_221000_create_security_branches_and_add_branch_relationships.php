<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_branches', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->string('city', 120)->nullable();
            $table->string('address', 180)->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('domain')->constrained('security_branches')->nullOnDelete();
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('user_id')->constrained('security_branches')->nullOnDelete();
            }
        });

        Schema::table('billing_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('billing_documents', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('order_id')->constrained('security_branches')->nullOnDelete();
            }
        });

        $defaultBranchId = $this->ensureDefaultBranch();

        DB::table('users')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);

        $userBranches = DB::table('users')->pluck('branch_id', 'id');

        DB::table('orders')->select('id', 'user_id', 'branch_id')->orderBy('id')->get()->each(function (object $order) use ($userBranches, $defaultBranchId): void {
            if ($order->branch_id) {
                return;
            }

            $branchId = (int) ($userBranches[$order->user_id] ?? $defaultBranchId);
            DB::table('orders')->where('id', $order->id)->update(['branch_id' => $branchId]);
        });

        $orderBranches = DB::table('orders')->pluck('branch_id', 'id');

        DB::table('billing_documents')->select('id', 'order_id', 'branch_id')->orderBy('id')->get()->each(function (object $document) use ($orderBranches, $defaultBranchId): void {
            if ($document->branch_id) {
                return;
            }

            $branchId = $document->order_id ? (int) ($orderBranches[$document->order_id] ?? $defaultBranchId) : $defaultBranchId;
            DB::table('billing_documents')->where('id', $document->id)->update(['branch_id' => $branchId]);
        });
    }

    public function down(): void
    {
        Schema::table('billing_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('billing_documents', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });

        Schema::dropIfExists('security_branches');
    }

    private function ensureDefaultBranch(): int
    {
        $defaultBranchId = DB::table('security_branches')->where('is_default', true)->value('id');

        if ($defaultBranchId) {
            return (int) $defaultBranchId;
        }

        $existing = DB::table('security_branches')->where('code', 'MAIN')->value('id');

        if ($existing) {
            DB::table('security_branches')->where('id', $existing)->update(['is_default' => true, 'is_active' => true]);

            return (int) $existing;
        }

        return (int) DB::table('security_branches')->insertGetId([
            'code' => 'MAIN',
            'name' => 'Sucursal principal',
            'city' => null,
            'address' => null,
            'phone' => null,
            'is_active' => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
