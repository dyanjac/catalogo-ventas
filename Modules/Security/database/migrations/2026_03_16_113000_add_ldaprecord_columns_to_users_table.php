<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('users', function (Blueprint $table) use ($driver): void {
            if (! Schema::hasColumn('users', 'guid')) {
                $table->string('guid')->nullable()->after('role');

                if ($driver !== 'sqlsrv') {
                    $table->unique('guid');
                }
            }

            if (! Schema::hasColumn('users', 'domain')) {
                $table->string('domain')->nullable()->after('guid');
            }
        });

        if ($driver === 'sqlsrv' && Schema::hasColumn('users', 'guid')) {
            DB::statement('create unique index users_guid_unique on users (guid) where guid is not null');
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'domain')) {
                $table->dropColumn('domain');
            }

            if (Schema::hasColumn('users', 'guid')) {
                $table->dropUnique('users_guid_unique');
                $table->dropColumn('guid');
            }
        });
    }
};
