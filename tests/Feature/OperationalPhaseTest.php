<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\Accounting\Enums\EconomicEventStatus;
use Modules\Accounting\Enums\EconomicEventType;
use Modules\Accounting\Jobs\ProcessEconomicEventJob;
use Modules\Accounting\Models\AccountingEconomicEvent;
use Modules\Accounting\Models\AccountingEntry;
use Modules\Operations\Models\OperationalIncident;
use Modules\Operations\Models\OperationalReconciliationIssue;
use Modules\Operations\Services\OperationalReconciliationService;
use Modules\Operations\Services\OperationalRecoveryService;
use Modules\Security\Database\Seeders\SecurityDatabaseSeeder;
use Tests\TestCase;

final class OperationalPhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_organization_produces_a_durable_passed_reconciliation(): void
    {
        $organization = $this->organization('OPS-CLEAN');

        $run = app(OperationalReconciliationService::class)->run((int) $organization->id, 'test');

        $this->assertSame('passed', $run->status);
        $this->assertSame(0, $run->issue_count);
        $this->assertNotNull($run->finished_at);
        $this->assertNotEmpty($run->correlation_id);
        $this->assertDatabaseHas('operational_reconciliation_runs', [
            'id' => $run->id,
            'organization_id' => $organization->id,
            'status' => 'passed',
        ]);
    }

    public function test_stale_processing_creates_one_deduplicated_incident_and_safe_recovery_resolves_it(): void
    {
        Queue::fake();
        $organization = $this->organization('OPS-INCIDENT');
        $event = $this->economicEvent($organization, EconomicEventStatus::Processing);
        DB::table('accounting_economic_events')->where('id', $event->id)->update(['updated_at' => now()->subHour()]);

        $first = app(OperationalReconciliationService::class)->run((int) $organization->id, 'test');
        $second = app(OperationalReconciliationService::class)->run((int) $organization->id, 'test');

        $this->assertSame('degraded', $first->status);
        $this->assertTrue($first->issues->contains('issue_code', 'ACC_STALE_PROCESSING'));
        $this->assertDatabaseCount('operational_incidents', 1);
        $this->assertSame(2, OperationalIncident::query()->sole()->occurrences);

        app(OperationalRecoveryService::class)->recoverStaleEconomicEvents((int) $organization->id, 15, true);
        $repaired = app(OperationalReconciliationService::class)->run((int) $organization->id, 'test');

        $this->assertSame('passed', $repaired->status);
        $this->assertSame('resolved', OperationalIncident::query()->sole()->status);
        $this->assertDatabaseHas('operational_incident_events', ['event_type' => 'resolved']);
    }

    public function test_reconciliation_evidence_is_immutable(): void
    {
        $organization = $this->organization('OPS-EVIDENCE');
        $this->economicEvent($organization, EconomicEventStatus::Processed);
        $run = app(OperationalReconciliationService::class)->run((int) $organization->id, 'test');
        $issue = OperationalReconciliationIssue::query()->where('run_id', $run->id)->firstOrFail();

        $this->expectException(QueryException::class);
        DB::table('operational_reconciliation_issues')->where('id', $issue->id)->update(['severity' => 'warning']);
    }

    public function test_stale_event_recovery_is_dry_run_by_default_and_scoped_when_executed(): void
    {
        Queue::fake();
        $organization = $this->organization('OPS-RECOVERY');
        $foreign = $this->organization('OPS-FOREIGN');
        $event = $this->economicEvent($organization, EconomicEventStatus::Processing);
        $foreignEvent = $this->economicEvent($foreign, EconomicEventStatus::Processing);
        DB::table('accounting_economic_events')->whereIn('id', [$event->id, $foreignEvent->id])->update(['updated_at' => now()->subHour()]);

        $preview = app(OperationalRecoveryService::class)->recoverStaleEconomicEvents((int) $organization->id, 15, false);
        $this->assertSame([$event->id], $preview['event_ids']);
        $this->assertSame(EconomicEventStatus::Processing, $event->fresh()->status);

        $executed = app(OperationalRecoveryService::class)->recoverStaleEconomicEvents((int) $organization->id, 15, true);
        $this->assertSame(1, $executed['count']);
        $this->assertSame(EconomicEventStatus::Pending, $event->fresh()->status);
        $this->assertSame(EconomicEventStatus::Processing, $foreignEvent->fresh()->status);
        Queue::assertPushed(ProcessEconomicEventJob::class, fn (ProcessEconomicEventJob $job): bool => $job->eventId === $event->id && $job->organizationId === $organization->id);
    }

    public function test_database_rejects_adding_lines_to_a_posted_entry(): void
    {
        $organization = $this->organization('OPS-POSTED');
        $entry = AccountingEntry::query()->create([
            'organization_id' => $organization->id,
            'origin' => 'manual',
            'entry_date' => now()->toDateString(),
            'period_year' => now()->year,
            'period_month' => now()->month,
            'reference' => 'POSTED-LOCK',
            'status' => 'posted',
            'total_debit' => 10,
            'total_credit' => 10,
            'posted_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('accounting_entry_lines')->insert([
            'organization_id' => $organization->id,
            'accounting_entry_id' => $entry->id,
            'account_code' => '101',
            'debit' => 10,
            'credit' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_cross_tenant_event_and_entry_reversal_links(): void
    {
        $organization = $this->organization('OPS-REV-A');
        $foreign = $this->organization('OPS-REV-B');
        $originalEvent = $this->economicEvent($organization, EconomicEventStatus::Pending);

        $eventBlocked = false;
        try {
            AccountingEconomicEvent::query()->create([
                'organization_id' => $foreign->id,
                'event_type' => EconomicEventType::EntryReversal,
                'status' => EconomicEventStatus::Pending,
                'idempotency_key' => 'cross-tenant-event-reversal',
                'payload_hash' => hash('sha256', 'cross-tenant-event-reversal'),
                'source_type' => Organization::class,
                'source_id' => 999,
                'payload' => ['original_event_id' => $originalEvent->id],
                'occurred_at' => now(),
                'reversal_of_event_id' => $originalEvent->id,
            ]);
        } catch (QueryException) {
            $eventBlocked = true;
        }
        $this->assertTrue($eventBlocked);

        $originalEntry = AccountingEntry::query()->create([
            'organization_id' => $organization->id, 'origin' => 'manual',
            'entry_date' => now()->toDateString(), 'period_year' => now()->year,
            'period_month' => now()->month, 'status' => 'draft',
            'total_debit' => 0, 'total_credit' => 0,
        ]);
        $entryBlocked = false;
        try {
            AccountingEntry::query()->create([
                'organization_id' => $foreign->id, 'origin' => 'manual',
                'reversal_of_id' => $originalEntry->id,
                'entry_date' => now()->toDateString(), 'period_year' => now()->year,
                'period_month' => now()->month, 'status' => 'draft',
                'total_debit' => 0, 'total_credit' => 0,
            ]);
        } catch (QueryException) {
            $entryBlocked = true;
        }
        $this->assertTrue($entryBlocked);
    }

    public function test_reconciliation_detects_reversed_event_without_a_compensating_event(): void
    {
        $organization = $this->organization('OPS-REV-MISSING');
        $event = $this->economicEvent($organization, EconomicEventStatus::Pending);
        $entry = AccountingEntry::query()->create([
            'organization_id' => $organization->id,
            'economic_event_id' => $event->id,
            'origin' => 'economic_event',
            'payload_hash' => $event->payload_hash,
            'entry_date' => $event->occurred_at->toDateString(),
            'period_year' => $event->occurred_at->year,
            'period_month' => $event->occurred_at->month,
            'status' => 'posting',
            'total_debit' => 10,
            'total_credit' => 10,
        ]);
        $entry->lines()->createMany([
            ['organization_id' => $organization->id, 'account_code' => '101', 'debit' => 10, 'credit' => 0],
            ['organization_id' => $organization->id, 'account_code' => '401', 'debit' => 0, 'credit' => 10],
        ]);
        $entry->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
        $event->forceFill([
            'status' => EconomicEventStatus::Reversed,
            'processed_entry_id' => $entry->id,
            'configuration_snapshot' => ['version' => 1],
            'processed_at' => now(),
        ])->save();

        $run = app(OperationalReconciliationService::class)->run((int) $organization->id, 'test');

        $this->assertSame('failed', $run->status);
        $this->assertTrue($run->issues->contains('issue_code', 'ACC_REVERSED_WITHOUT_COMPENSATION'));
    }

    public function test_readiness_endpoint_reports_runtime_dependencies_without_exposing_tenant_data(): void
    {
        $response = $this->getJson('/health/ready');

        $response->assertOk()
            ->assertJsonPath('ready', true)
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.cache.ok', true)
            ->assertJsonMissingPath('organization_id');
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
    }

    public function test_super_admin_can_open_the_tenant_scoped_operations_dashboard(): void
    {
        $organization = $this->organization('OPS-DASHBOARD');
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => 'super_admin']);
        $this->seed(SecurityDatabaseSeeder::class);

        $this->actingAs($user)
            ->get(route('admin.operations.index'))
            ->assertOk()
            ->assertSee('Operaciones y observabilidad')
            ->assertSee('Ejecutar conciliación');
    }

    public function test_strict_readiness_requires_a_non_failed_recent_run_for_every_active_organization(): void
    {
        config()->set('operations.readiness.require_recent_reconciliation', true);
        Organization::query()->update(['status' => 'suspended']);
        $first = $this->organization('OPS-READY-A');
        $second = $this->organization('OPS-READY-B');

        app(OperationalReconciliationService::class)->run((int) $first->id, 'test');
        $this->getJson('/health/ready')->assertStatus(503)->assertJsonPath('checks.reconciliation.ok', false);

        app(OperationalReconciliationService::class)->run((int) $second->id, 'test');
        $this->getJson('/health/ready')->assertOk()->assertJsonPath('checks.reconciliation.ok', true);

        $this->economicEvent($first, EconomicEventStatus::Processed);
        app(OperationalReconciliationService::class)->run((int) $first->id, 'test');
        $this->getJson('/health/ready')->assertStatus(503)->assertJsonPath('checks.reconciliation.ok', false);
    }

    public function test_sync_reconciliation_command_fails_the_shell_on_critical_findings(): void
    {
        $clean = $this->organization('OPS-COMMAND-OK');
        $broken = $this->organization('OPS-COMMAND-FAIL');
        $this->economicEvent($broken, EconomicEventStatus::Processed);

        $this->artisan('operations:reconcile', ['organization' => $clean->id, '--sync' => true])
            ->assertSuccessful();
        $this->artisan('operations:reconcile', ['organization' => $broken->id, '--sync' => true])
            ->assertFailed();
    }

    private function organization(string $code): Organization
    {
        return Organization::query()->create([
            'code' => $code,
            'name' => 'Organization '.$code,
            'slug' => strtolower($code),
            'status' => 'active',
            'environment' => 'demo',
            'is_default' => false,
            'settings_json' => [],
        ]);
    }

    private function economicEvent(Organization $organization, EconomicEventStatus $status): AccountingEconomicEvent
    {
        $key = strtolower($organization->code).':'.strtolower($status->value);

        return AccountingEconomicEvent::query()->create([
            'organization_id' => $organization->id,
            'event_type' => EconomicEventType::PaymentReceived,
            'status' => $status,
            'idempotency_key' => $key,
            'payload_hash' => hash('sha256', $key),
            'source_type' => Organization::class,
            'source_id' => $organization->id,
            'source_code' => $organization->code,
            'payload' => ['amount' => '10.00'],
            'configuration_snapshot' => $status === EconomicEventStatus::Processed ? ['version' => 1] : null,
            'occurred_at' => now()->subHour(),
            'processed_at' => $status === EconomicEventStatus::Processed ? now() : null,
        ]);
    }
}
