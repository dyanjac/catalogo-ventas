<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\AdminTheme\Database\Seeders\AdminThemeDatabaseSeeder;
use Modules\Billing\Database\Seeders\BillingDatabaseSeeder;
use Modules\Catalog\Database\Seeders\CatalogDatabaseSeeder;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Orders\Database\Seeders\OrdersDatabaseSeeder;
use Modules\Sales\Database\Seeders\SalesDatabaseSeeder;
use Modules\Security\Database\Seeders\SecurityDatabaseSeeder;

class ModuleDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreDatabaseSeeder::class,
            AdminDatabaseSeeder::class,
            AdminThemeDatabaseSeeder::class,
            CatalogDatabaseSeeder::class,
            OrdersDatabaseSeeder::class,
            SalesDatabaseSeeder::class,
            AccountingDatabaseSeeder::class,
            BillingDatabaseSeeder::class,
            // Keep security last so it can attach roles to users already seeded above.
            SecurityDatabaseSeeder::class,
        ]);
    }
}
