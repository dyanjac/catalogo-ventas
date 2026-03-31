<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DefaultOrganizationSeeder::class,
            ModuleDatabaseSeeder::class,
            SuperAdminSeeder::class,
            CategorySeeder::class,
            UnitMeasureSeeder::class,
        ]);
    }
}
