<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Entities\Category;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Catalog\Enums\ProductType;
use Modules\Security\Models\SecurityBranch;
use Modules\Transport\Models\TransportGuide;
use Modules\Transport\Models\TransportSetting;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TransportGuideMysqlConcurrencyTest extends TestCase
{
    public function test_mysql_serializes_idempotent_replay_and_correlative_assignment(): void
    {
        if (DB::getDriverName() !== 'mysql' || getenv('PHASE07_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Prueba opt-in exclusiva para MySQL/InnoDB.');
        }

        $scope = $this->scope();
        $sameKey = $this->runWorkers($scope, ['phase07-same-key', 'phase07-same-key']);
        $this->assertSame(2, collect($sameKey)->where('exit', 0)->count(), json_encode($sameKey));
        $this->assertSame(1, TransportGuide::query()->where('organization_id', $scope['organization']->id)->count());
        $this->assertCount(1, collect($sameKey)->pluck('output')->map(fn (string $value): string => trim($value))->unique());

        $differentKeys = $this->runWorkers($scope, ['phase07-number-a', 'phase07-number-b']);
        $this->assertSame(2, collect($differentKeys)->where('exit', 0)->count(), json_encode($differentKeys));
        $guides = TransportGuide::query()->where('organization_id', $scope['organization']->id)->orderBy('number')->get();
        $this->assertCount(3, $guides);
        $this->assertCount(3, $guides->pluck('number')->unique());
        $this->assertSame(4, (int) DB::table('transport_guide_counters')->where('organization_id', $scope['organization']->id)->value('next_number'));
    }

    /** @return array<int, array{exit:int,output:string,error:string}> */
    private function runWorkers(array $scope, array $keys): array
    {
        $processes = collect($keys)->map(function (string $key) use ($scope): Process {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/phase07_guide_worker.php'),
                (string) $scope['user']->id,
                (string) $scope['branch']->id,
                (string) $scope['product']->id,
                $key,
            ], base_path(), null, null, 90);
            $process->start();

            return $process;
        });

        return $processes->map(function (Process $process): array {
            $process->wait();

            return ['exit' => $process->getExitCode() ?? -1, 'output' => $process->getOutput(), 'error' => $process->getErrorOutput()];
        })->all();
    }

    /** @return array<string, mixed> */
    private function scope(): array
    {
        $suffix = uniqid();
        $organization = Organization::query()->create([
            'code' => 'F7M-'.$suffix, 'name' => 'Phase 07 MySQL', 'slug' => 'phase07-mysql-'.$suffix,
            'status' => 'active', 'environment' => 'demo', 'is_default' => true,
        ]);
        $branch = SecurityBranch::query()->create([
            'organization_id' => $organization->id, 'code' => 'F7B-'.$suffix, 'name' => 'Principal',
            'is_active' => true, 'is_default' => true,
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id, 'branch_id' => $branch->id]);
        $category = Category::query()->create([
            'organization_id' => $organization->id, 'name' => 'Physical F7', 'slug' => 'physical-f7-'.$suffix,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value,
        ]);
        $product = Product::query()->create([
            'organization_id' => $organization->id, 'category_id' => $category->id, 'name' => 'Producto F7', 'sku' => 'F7M-'.$suffix,
            'slug' => 'product-f7-'.$suffix, 'tax_affectation' => 'Gravado', 'product_type' => ProductType::PhysicalGood->value,
            'accounting_treatment' => ProductAccountingTreatment::Inherit->value, 'price' => 10, 'stock' => 10, 'min_stock' => 0, 'is_active' => true,
        ]);
        TransportSetting::query()->create([
            'organization_id' => $organization->id, 'enabled' => true, 'environment' => 'simulation',
            'provider' => 'simulation', 'dispatch_mode' => 'queue', 'queue_name' => 'transport',
            'sender_series' => 'T001', 'carrier_series' => 'V001',
        ]);
        DB::table('organization_entitlements')->insert([
            'organization_id' => $organization->id,
            'capability_id' => DB::table('saas_capabilities')->where('code', 'transport.gre')->value('id'),
            'state' => 'enabled', 'source' => 'phase07-test', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('organization', 'branch', 'user', 'product');
    }
}
