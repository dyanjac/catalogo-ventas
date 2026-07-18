<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Operations\Services\OperationalReconciliationService;
use Tests\TestCase;

final class OperationalReconciliationLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_chunks_a_large_event_set_with_bounded_memory(): void
    {
        if (env('RUN_OPERATIONAL_LOAD_TESTS') !== '1') {
            $this->markTestSkipped('Defina RUN_OPERATIONAL_LOAD_TESTS=1 para ejecutar la prueba de carga.');
        }

        $organization = Organization::query()->create([
            'code' => 'OPS-LOAD', 'name' => 'Operations Load', 'slug' => 'ops-load',
            'status' => 'active', 'environment' => 'demo', 'is_default' => false, 'settings_json' => [],
        ]);
        $now = now();
        foreach (array_chunk(range(1, 2000), 250) as $ids) {
            DB::table('accounting_economic_events')->insert(array_map(fn (int $id): array => [
                'organization_id' => $organization->id,
                'event_type' => 'payment_received',
                'status' => 'pending',
                'idempotency_key' => "load:{$id}",
                'payload_hash' => hash('sha256', "load:{$id}"),
                'source_type' => Organization::class,
                'source_id' => $id,
                'source_code' => "LOAD-{$id}",
                'payload' => json_encode(['amount' => '1.00'], JSON_THROW_ON_ERROR),
                'attempts' => 0,
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $ids));
        }

        $memoryBefore = memory_get_usage(true);
        $run = app(OperationalReconciliationService::class)->run((int) $organization->id, 'load_test');
        $memoryGrowth = memory_get_peak_usage(true) - $memoryBefore;

        $this->assertSame(2000, $run->checked_economic_events);
        $this->assertSame('passed', $run->status);
        $this->assertLessThan(64 * 1024 * 1024, $memoryGrowth);
    }
}
