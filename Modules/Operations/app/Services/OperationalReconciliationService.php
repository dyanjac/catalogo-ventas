<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use App\Models\Organization;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Accounting\Enums\EconomicEventStatus;
use Modules\Accounting\Enums\EconomicEventType;
use Modules\Accounting\Models\AccountingActivationItem;
use Modules\Accounting\Models\AccountingEconomicEvent;
use Modules\Accounting\Models\AccountingEntry;
use Modules\Catalog\Entities\InventoryBalance;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\InventoryDocumentItem;
use Modules\Catalog\Entities\InventoryMovement;
use Modules\Catalog\Enums\InventoryDocumentStatus;
use Modules\Catalog\Enums\InventoryDocumentType;
use Modules\Catalog\Enums\InventoryMovementType;
use Modules\Catalog\Services\InventoryReconciliationService;
use Modules\Operations\Models\OperationalReconciliationIssue;
use Modules\Operations\Models\OperationalReconciliationRun;
use Throwable;

final class OperationalReconciliationService
{
    public function __construct(
        private readonly InventoryReconciliationService $inventoryReconciliation,
        private readonly OperationalIncidentService $incidents,
    ) {}

    public function run(int $organizationId, string $trigger = 'manual', ?int $actorId = null): OperationalReconciliationRun
    {
        $lock = Cache::lock("operations:reconciliation:{$organizationId}", 950);
        if (! $lock->get()) {
            throw new DomainException('Ya existe una conciliación activa para la organización.');
        }

        try {
            return $this->runLocked($organizationId, $trigger, $actorId);
        } finally {
            $lock->release();
        }
    }

    private function runLocked(int $organizationId, string $trigger, ?int $actorId): OperationalReconciliationRun
    {
        Organization::query()->findOrFail($organizationId);
        $started = hrtime(true);
        $run = OperationalReconciliationRun::query()->create([
            'organization_id' => $organizationId, 'correlation_id' => (string) Str::uuid(),
            'trigger' => $trigger, 'status' => 'running', 'captured_at' => now('UTC'),
            'started_at' => now('UTC'), 'created_by' => $actorId,
        ]);
        Log::channel('operations')->info('erp.reconciliation.started', [
            'organization_id' => $organizationId, 'run_id' => $run->id,
            'correlation_id' => $run->correlation_id, 'trigger' => $trigger,
        ]);

        try {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            DB::transaction(function () use ($organizationId, $run, $started): void {
                $inventoryRun = $this->inventoryReconciliation->run($organizationId);
                foreach ($inventoryRun->issues as $legacyIssue) {
                    $this->issue($run, 'inventory_balance', strtoupper((string) $legacyIssue->issue_type),
                        str_contains((string) $legacyIssue->issue_type, 'legacy') ? 'warning' : 'critical',
                        InventoryBalance::class, null, null,
                        ['value' => $legacyIssue->expected_value], ['value' => $legacyIssue->actual_value],
                        array_filter(['product_id' => $legacyIssue->product_id, 'branch_id' => $legacyIssue->branch_id, 'warehouse_id' => $legacyIssue->warehouse_id]));
                }
                $balanceCount = $this->reconcileLedgerChains($run);
                $documentCount = $this->reconcileDocuments($run);
                [$eventCount, $entryCount] = $this->reconcileAccounting($run);

                $issueCount = $run->issues()->count();
                $criticalCount = $run->issues()->where('severity', 'critical')->count();
                $warningCount = $run->issues()->where('severity', 'warning')->count();
                $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);
                $metrics = $this->metrics($organizationId, $run, [
                    'inventory_balances' => $balanceCount, 'inventory_documents' => $documentCount,
                    'economic_events' => $eventCount, 'accounting_entries' => $entryCount,
                ]);
                $run->forceFill([
                    'status' => $criticalCount > 0 ? 'failed' : ($warningCount > 0 ? 'degraded' : 'passed'),
                    'finished_at' => now('UTC'), 'duration_ms' => $durationMs,
                    'checked_inventory_balances' => $balanceCount,
                    'checked_inventory_documents' => $documentCount,
                    'checked_economic_events' => $eventCount, 'checked_accounting_entries' => $entryCount,
                    'issue_count' => $issueCount, 'critical_count' => $criticalCount,
                    'warning_count' => $warningCount, 'metrics' => $metrics,
                ])->save();
            }, 3);

            $run->refresh();
            $this->incidents->synchronize($run->fresh('issues'));
            Log::channel('operations')->info('erp.reconciliation.completed', [
                'organization_id' => $organizationId, 'run_id' => $run->id,
                'correlation_id' => $run->correlation_id, 'status' => $run->status,
                'duration_ms' => $run->duration_ms, 'issue_count' => $run->issue_count,
                'critical_count' => $run->critical_count, 'warning_count' => $run->warning_count,
            ]);
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'error', 'finished_at' => now('UTC'),
                'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'error_code' => class_basename($exception),
                'error_message' => 'La conciliación no pudo completarse; revise el log operativo mediante su correlation_id.',
            ])->save();
            Log::channel('operations')->error('erp.reconciliation.failed', [
                'organization_id' => $organizationId, 'run_id' => $run->id,
                'correlation_id' => $run->correlation_id, 'error_class' => $exception::class,
                'error_code' => $exception->getCode(),
            ]);
            throw $exception;
        }

        return $run->fresh(['issues', 'incidents']);
    }

    private function reconcileLedgerChains(OperationalReconciliationRun $run): int
    {
        $checked = 0;
        InventoryBalance::query()->where('organization_id', $run->organization_id)->orderBy('id')
            ->chunkById(250, function (Collection $balances) use ($run, &$checked): void {
                $movementsByBalance = InventoryMovement::query()->where('organization_id', $run->organization_id)
                    ->where('ledger_generation', 1)->whereIn('inventory_balance_id', $balances->pluck('id'))
                    ->with('reversalOf')->orderBy('balance_version')->orderBy('id')->get()->groupBy('inventory_balance_id');
                foreach ($balances as $balance) {
                    $checked++;
                    $expectedLocation = $balance->warehouse_id ? 'warehouse:'.$balance->warehouse_id : 'unallocated:'.$balance->branch_id;
                    if ($balance->location_key !== $expectedLocation) {
                        $this->issue($run, 'inventory_balance', 'INV_SCOPE_MISMATCH', 'critical', InventoryBalance::class,
                            $balance->id, $balance->location_key, ['location_key' => $expectedLocation], ['location_key' => $balance->location_key]);
                    }
                    if ((int) $balance->physical_stock < 0 || (int) $balance->reserved_stock < 0
                        || (int) $balance->in_transit_stock < 0 || (int) $balance->reserved_stock > (int) $balance->physical_stock) {
                        $this->issue($run, 'inventory_balance', 'INV_NEGATIVE_OR_OVERRESERVED', 'critical', InventoryBalance::class,
                            $balance->id, $balance->location_key,
                            ['physical_stock_gte' => 0, 'reserved_stock_between' => [0, (int) $balance->physical_stock], 'in_transit_stock_gte' => 0],
                            ['physical_stock' => (int) $balance->physical_stock, 'reserved_stock' => (int) $balance->reserved_stock, 'in_transit_stock' => (int) $balance->in_transit_stock]);
                    }
                    $movements = $movementsByBalance->get($balance->id, collect());
                    $previous = null;
                    $expectedVersion = 1;
                    foreach ($movements as $movement) {
                        if ((int) $movement->organization_id !== (int) $balance->organization_id
                            || (int) $movement->product_id !== (int) $balance->product_id
                            || (int) $movement->branch_id !== (int) $balance->branch_id
                            || (int) ($movement->warehouse_id ?? 0) !== (int) ($balance->warehouse_id ?? 0)) {
                            $this->issue($run, 'inventory_balance', 'INV_SCOPE_MISMATCH', 'critical', InventoryMovement::class,
                                $movement->id, $movement->reference_code, null, null, ['balance_id' => $balance->id]);
                        }
                        if ((int) $movement->balance_version !== $expectedVersion) {
                            $this->issue($run, 'inventory_balance', 'INV_VERSION_GAP', 'critical', InventoryBalance::class,
                                $balance->id, $balance->location_key, ['balance_version' => $expectedVersion], ['balance_version' => (int) $movement->balance_version]);
                            $expectedVersion = (int) $movement->balance_version;
                        }
                        if ((int) $movement->stock_after !== (int) $movement->stock_before + (int) $movement->quantity) {
                            $this->issue($run, 'inventory_balance', 'INV_EQUATION_MISMATCH', 'critical', InventoryMovement::class,
                                $movement->id, $movement->reference_code,
                                ['stock_after' => (int) $movement->stock_before + (int) $movement->quantity], ['stock_after' => (int) $movement->stock_after]);
                        }
                        if ($previous && ((int) $movement->stock_before !== (int) $previous->stock_after
                            || abs((float) $movement->average_cost_before - (float) $previous->average_cost_after) > 0.0001)) {
                            $this->issue($run, 'inventory_balance', 'INV_CHAIN_BREAK', 'critical', InventoryMovement::class,
                                $movement->id, $movement->reference_code,
                                ['stock_before' => (int) $previous->stock_after, 'average_cost_before' => (float) $previous->average_cost_after],
                                ['stock_before' => (int) $movement->stock_before, 'average_cost_before' => (float) $movement->average_cost_before]);
                        }
                        $expectedTotal = round(abs((int) $movement->quantity) * (float) $movement->unit_cost, 4);
                        if (abs($expectedTotal - (float) $movement->total_cost) > 0.0001) {
                            $this->issue($run, 'inventory_balance', 'INV_COST_DRIFT', 'critical', InventoryMovement::class,
                                $movement->id, $movement->reference_code, ['total_cost' => $expectedTotal], ['total_cost' => (float) $movement->total_cost]);
                        }
                        if ($movement->movement_type === InventoryMovementType::Reversal && $movement->reversalOf) {
                            $expectedAverage = $this->expectedReversalAverage($movement, $movement->reversalOf);
                            if ($expectedAverage !== null && abs($expectedAverage - (float) $movement->average_cost_after) > 0.0001) {
                                $this->issue($run, 'inventory_balance', 'INV_REVERSAL_COST_MISMATCH', 'critical', InventoryMovement::class,
                                    $movement->id, $movement->reference_code, ['average_cost_after' => $expectedAverage], ['average_cost_after' => (float) $movement->average_cost_after]);
                            }
                        }
                        $previous = $movement;
                        $expectedVersion++;
                    }
                    if (! $previous && (int) $balance->version !== 0) {
                        $this->issue($run, 'inventory_balance', 'INV_LEDGER_MISSING', 'critical', InventoryBalance::class,
                            $balance->id, $balance->location_key, ['version' => 0], ['version' => (int) $balance->version]);
                    } elseif ($previous && ((int) $previous->stock_after !== (int) $balance->physical_stock
                        || abs((float) $previous->average_cost_after - (float) $balance->average_cost) > 0.0001
                        || (int) $previous->balance_version !== (int) $balance->version)) {
                        $this->issue($run, 'inventory_balance', 'INV_BALANCE_HEAD_MISMATCH', 'critical', InventoryBalance::class,
                            $balance->id, $balance->location_key,
                            ['physical_stock' => (int) $previous->stock_after, 'average_cost' => (float) $previous->average_cost_after, 'version' => (int) $previous->balance_version],
                            ['physical_stock' => (int) $balance->physical_stock, 'average_cost' => (float) $balance->average_cost, 'version' => (int) $balance->version]);
                    }
                }
            });

        return $checked;
    }

    private function reconcileDocuments(OperationalReconciliationRun $run): int
    {
        $checked = 0;
        InventoryDocument::query()->where('organization_id', $run->organization_id)->orderBy('id')
            ->with(['items.movement.reversalOf', 'items.balance'])->chunkById(200, function (Collection $documents) use ($run, &$checked): void {
                foreach ($documents as $document) {
                    $checked++;
                    if ($document->status !== InventoryDocumentStatus::Confirmed) {
                        continue;
                    }
                    if (! $document->confirmed_at) {
                        $this->issue($run, 'inventory_document', 'DOC_CONFIRMED_AT_MISSING', 'critical', InventoryDocument::class, $document->id, $document->code);
                    }
                    $movementIds = $document->items->pluck('inventory_movement_id')->filter();
                    foreach ($movementIds->duplicates()->unique() as $movementId) {
                        $this->issue($run, 'inventory_document', 'DOC_MOVEMENT_DUPLICATED', 'critical', InventoryDocument::class,
                            $document->id, $document->code, null, null, ['movement_id' => (int) $movementId]);
                    }
                    foreach ($document->items as $item) {
                        $movement = $item->movement;
                        if (! $movement) {
                            $this->issue($run, 'inventory_document', 'DOC_ITEM_MOVEMENT_MISSING', 'critical', InventoryDocumentItem::class,
                                $item->id, $document->code, ['movement' => 'required'], ['movement' => null], ['document_id' => $document->id]);
                            continue;
                        }
                        if ((int) $item->organization_id !== (int) $document->organization_id
                            || (int) $movement->organization_id !== (int) $document->organization_id
                            || (int) $movement->product_id !== (int) $item->product_id
                            || (int) $movement->branch_id !== (int) $document->branch_id
                            || (int) ($movement->warehouse_id ?? 0) !== (int) ($document->warehouse_id ?? 0)) {
                            $this->issue($run, 'inventory_document', 'DOC_SCOPE_MISMATCH', 'critical', InventoryDocumentItem::class,
                                $item->id, $document->code, null, null, ['document_id' => $document->id, 'movement_id' => $movement->id]);
                        }
                        $expectedQuantity = $this->expectedDocumentQuantity($document->document_type, $item);
                        if ($expectedQuantity !== null && (int) $movement->quantity !== $expectedQuantity) {
                            $this->issue($run, 'inventory_document', 'DOC_SIGN_QUANTITY_MISMATCH', 'critical', InventoryDocumentItem::class,
                                $item->id, $document->code, ['quantity' => $expectedQuantity], ['quantity' => (int) $movement->quantity], ['movement_id' => $movement->id]);
                        }
                        if ($document->document_type !== InventoryDocumentType::Compensation
                            && ($movement->reference_type !== InventoryDocument::class || (int) $movement->reference_id !== (int) $document->id)) {
                            $this->issue($run, 'inventory_document', 'DOC_REFERENCE_MISMATCH', 'critical', InventoryDocumentItem::class,
                                $item->id, $document->code, ['reference_id' => $document->id], ['reference_id' => $movement->reference_id]);
                        }
                        if ($document->document_type === InventoryDocumentType::Compensation
                            && ($movement->movement_type !== InventoryMovementType::Reversal || ! $movement->reversal_of_id)) {
                            $this->issue($run, 'inventory_document', 'DOC_REVERSAL_MISMATCH', 'critical', InventoryDocumentItem::class,
                                $item->id, $document->code, ['movement_type' => InventoryMovementType::Reversal->value], ['movement_type' => $movement->movement_type->value]);
                        }
                    }
                }
            });

        InventoryMovement::query()->where('organization_id', $run->organization_id)
            ->where('reference_type', InventoryDocument::class)->orderBy('id')->chunkById(500, function (Collection $movements) use ($run): void {
                foreach ($movements as $movement) {
                    $represented = InventoryDocumentItem::query()->where('organization_id', $run->organization_id)
                        ->where('inventory_movement_id', $movement->id)->exists();
                    $confirmed = InventoryDocument::query()->where('organization_id', $run->organization_id)
                        ->whereKey($movement->reference_id)->where('status', InventoryDocumentStatus::Confirmed->value)->exists();
                    if (! $represented || ! $confirmed) {
                        $this->issue($run, 'inventory_document', 'DOC_ORPHAN_MOVEMENT', 'critical', InventoryMovement::class,
                            $movement->id, $movement->reference_code, ['represented' => true, 'confirmed_document' => true],
                            ['represented' => $represented, 'confirmed_document' => $confirmed]);
                    }
                }
            });

        return $checked;
    }

    /** @return array{int,int} */
    private function reconcileAccounting(OperationalReconciliationRun $run): array
    {
        $eventsChecked = 0;
        AccountingEconomicEvent::query()->where('organization_id', $run->organization_id)->orderBy('id')
            ->with(['entry.lines', 'reversalOf.entry.lines'])->chunkById(250, function (Collection $events) use ($run, &$eventsChecked): void {
                foreach ($events as $event) {
                    $eventsChecked++;
                    $stable = in_array($event->status, [EconomicEventStatus::Processed, EconomicEventStatus::Reversed], true);
                    if ($stable && ! $event->entry) {
                        $this->issue($run, 'accounting', 'ACC_EVENT_ENTRY_MISSING', 'critical', AccountingEconomicEvent::class, $event->id, $event->source_code);
                        continue;
                    }
                    if (! $stable && $event->processed_entry_id) {
                        $this->issue($run, 'accounting', 'ACC_UNEXPECTED_ENTRY_LINK', 'critical', AccountingEconomicEvent::class,
                            $event->id, $event->source_code, ['processed_entry_id' => null], ['processed_entry_id' => $event->processed_entry_id]);
                    }
                    if ($event->status === EconomicEventStatus::Processing
                        && $event->updated_at?->lte($run->captured_at->subMinutes((int) config('operations.reconciliation.stale_after_minutes', 15)))) {
                        $this->issue($run, 'accounting', 'ACC_STALE_PROCESSING', 'warning', AccountingEconomicEvent::class,
                            $event->id, $event->source_code, null, null, ['updated_at' => $event->updated_at?->toISOString()]);
                    }
                    if (! $event->entry) {
                        continue;
                    }
                    $entry = $event->entry;
                    if ((int) $entry->organization_id !== (int) $event->organization_id
                        || (int) $entry->economic_event_id !== (int) $event->id
                        || (int) $event->processed_entry_id !== (int) $entry->id) {
                        $this->issue($run, 'accounting', 'ACC_RECIPROCAL_LINK_MISMATCH', 'critical', AccountingEconomicEvent::class,
                            $event->id, $event->source_code, null, null, ['entry_id' => $entry->id]);
                    }
                    if ($entry->payload_hash !== $event->payload_hash) {
                        $this->issue($run, 'accounting', 'ACC_HASH_MISMATCH', 'critical', AccountingEconomicEvent::class,
                            $event->id, $event->source_code, ['payload_hash' => $event->payload_hash], ['payload_hash' => $entry->payload_hash]);
                    }
                    if ($entry->origin !== 'economic_event' || $entry->status !== 'posted') {
                        $this->issue($run, 'accounting', 'ACC_ENTRY_STATE_MISMATCH', 'critical', AccountingEconomicEvent::class,
                            $event->id, $event->source_code, ['origin' => 'economic_event', 'status' => 'posted'], ['origin' => $entry->origin, 'status' => $entry->status]);
                    }
                    if ($event->occurred_at && ($entry->entry_date?->toDateString() !== $event->occurred_at->toDateString()
                        || (int) $entry->period_year !== (int) $event->occurred_at->year || (int) $entry->period_month !== (int) $event->occurred_at->month)) {
                        $this->issue($run, 'accounting', 'ACC_PERIOD_MISMATCH', 'critical', AccountingEconomicEvent::class,
                            $event->id, $event->source_code, ['date' => $event->occurred_at->toDateString()], ['date' => $entry->entry_date?->toDateString()]);
                    }
                    if (! is_array($event->configuration_snapshot) || $event->configuration_snapshot === []) {
                        $this->issue($run, 'accounting', 'ACC_SNAPSHOT_MISSING', 'critical', AccountingEconomicEvent::class, $event->id, $event->source_code);
                    }
                    if ($event->status === EconomicEventStatus::Reversed) {
                        $hasCompensation = AccountingEconomicEvent::query()
                            ->where('organization_id', $run->organization_id)
                            ->where('event_type', EconomicEventType::EntryReversal->value)
                            ->where('reversal_of_event_id', $event->id)
                            ->where('status', EconomicEventStatus::Processed->value)
                            ->whereNotNull('processed_entry_id')
                            ->exists();
                        if (! $hasCompensation) {
                            $this->issue($run, 'accounting', 'ACC_REVERSED_WITHOUT_COMPENSATION', 'critical',
                                AccountingEconomicEvent::class, $event->id, $event->source_code);
                        }
                    }
                    if ($event->event_type === EconomicEventType::EntryReversal) {
                        $this->reconcileReversal($run, $event);
                    }
                }
            });

        $entriesChecked = 0;
        AccountingEntry::query()->where('organization_id', $run->organization_id)->orderBy('id')
            ->with(['lines', 'economicEvent', 'reversalOf'])->chunkById(250, function (Collection $entries) use ($run, &$entriesChecked): void {
                foreach ($entries as $entry) {
                    $entriesChecked++;
                    if ($entry->origin === 'economic_event' && ! $entry->economicEvent) {
                        $this->issue($run, 'accounting', 'ACC_ENTRY_EVENT_MISSING', 'critical', AccountingEntry::class, $entry->id, $entry->reference);
                    }
                    if ($entry->origin === 'manual' && $entry->economic_event_id) {
                        $this->issue($run, 'accounting', 'ACC_MANUAL_EVENT_LINK', 'critical', AccountingEntry::class, $entry->id, $entry->reference);
                    }
                    if ($entry->reversal_of_id && (! $entry->reversalOf
                        || (int) $entry->reversalOf->organization_id !== (int) $entry->organization_id)) {
                        $this->issue($run, 'accounting', 'ACC_ENTRY_REVERSAL_SCOPE_MISMATCH', 'critical', AccountingEntry::class, $entry->id, $entry->reference);
                    }
                    if ($entry->status !== 'posted') {
                        continue;
                    }
                    $lineDebit = round((float) $entry->lines->sum(fn ($line) => (float) $line->debit), 2);
                    $lineCredit = round((float) $entry->lines->sum(fn ($line) => (float) $line->credit), 2);
                    if ($entry->lines->contains(fn ($line) => (int) $line->organization_id !== (int) $entry->organization_id)) {
                        $this->issue($run, 'accounting', 'ACC_LINE_TENANT_MISMATCH', 'critical', AccountingEntry::class, $entry->id, $entry->reference);
                    }
                    if (abs($lineDebit - (float) $entry->total_debit) > 0.001 || abs($lineCredit - (float) $entry->total_credit) > 0.001) {
                        $this->issue($run, 'accounting', 'ACC_HEADER_TOTAL_MISMATCH', 'critical', AccountingEntry::class,
                            $entry->id, $entry->reference, ['debit' => $lineDebit, 'credit' => $lineCredit],
                            ['debit' => (float) $entry->total_debit, 'credit' => (float) $entry->total_credit]);
                    }
                    if ($lineDebit <= 0 || abs($lineDebit - $lineCredit) > 0.001) {
                        $this->issue($run, 'accounting', 'ACC_UNBALANCED', 'critical', AccountingEntry::class,
                            $entry->id, $entry->reference, ['balanced' => true], ['debit' => $lineDebit, 'credit' => $lineCredit]);
                    }
                }
            });

        AccountingActivationItem::query()->where('organization_id', $run->organization_id)->where('status', 'processed')
            ->where(function ($query): void {
                $query->whereNull('accounting_economic_event_id')->orWhereNull('accounting_entry_id');
            })->each(fn ($item) => $this->issue($run, 'accounting', 'ACC_ACTIVATION_RESULT_MISSING', 'critical',
                AccountingActivationItem::class, $item->id, $item->source_code));

        return [$eventsChecked, $entriesChecked];
    }

    private function reconcileReversal(OperationalReconciliationRun $run, AccountingEconomicEvent $event): void
    {
        $original = $event->reversalOf;
        if (! $original?->entry || ! $event->entry
            || (int) $original->organization_id !== (int) $event->organization_id
            || (int) $original->entry->organization_id !== (int) $event->organization_id
            || (int) $event->entry->reversal_of_id !== (int) $original->entry->id) {
            $this->issue($run, 'accounting', 'ACC_REVERSAL_MISMATCH', 'critical', AccountingEconomicEvent::class, $event->id, $event->source_code);
            return;
        }
        $group = fn (Collection $lines): array => $lines->groupBy(fn ($line) => $line->account_code.'|'.($line->product_id ?? 0))
            ->map(fn (Collection $rows) => ['debit' => round((float) $rows->sum(fn ($line) => (float) $line->debit), 2),
                'credit' => round((float) $rows->sum(fn ($line) => (float) $line->credit), 2)])->sortKeys()->all();
        $originalLines = $group($original->entry->lines);
        $reversalLines = $group($event->entry->lines);
        $expected = collect($originalLines)->map(fn (array $line) => ['debit' => $line['credit'], 'credit' => $line['debit']])->all();
        if ($expected !== $reversalLines) {
            $this->issue($run, 'accounting', 'ACC_REVERSAL_MISMATCH', 'critical', AccountingEconomicEvent::class,
                $event->id, $event->source_code, ['line_groups' => $expected], ['line_groups' => $reversalLines]);
        }
    }

    private function expectedDocumentQuantity(InventoryDocumentType $type, InventoryDocumentItem $item): ?int
    {
        return match ($type) {
            InventoryDocumentType::Inbound, InventoryDocumentType::Receipt,
            InventoryDocumentType::CustomerReturn, InventoryDocumentType::OpeningStock => abs((int) $item->quantity),
            InventoryDocumentType::Outbound, InventoryDocumentType::Dispatch,
            InventoryDocumentType::SupplierReturn => -abs((int) $item->quantity),
            InventoryDocumentType::StockAdjustment => $item->target_quantity === null || ! $item->movement
                ? null : (int) $item->target_quantity - (int) $item->movement->stock_before,
            InventoryDocumentType::Compensation => $item->movement?->reversalOf ? -(int) $item->movement->reversalOf->quantity : null,
        };
    }

    private function expectedReversalAverage(InventoryMovement $reversal, InventoryMovement $original): ?float
    {
        $stockAfter = (int) $reversal->stock_after;
        $valueBefore = (int) $reversal->stock_before * (float) $reversal->average_cost_before;
        $valueAfter = $valueBefore + ((int) $reversal->quantity * (float) $original->unit_cost);
        if ($stockAfter < 0 || $valueAfter < -0.0001) {
            return null;
        }

        return $stockAfter === 0 ? 0.0 : round($valueAfter / $stockAfter, 4);
    }

    /** @param array<string,mixed>|null $expected @param array<string,mixed>|null $actual @param array<string,mixed> $context */
    private function issue(OperationalReconciliationRun $run, string $domain, string $code, string $severity,
        ?string $sourceType = null, ?int $sourceId = null, ?string $sourceCode = null,
        ?array $expected = null, ?array $actual = null, array $context = []): void
    {
        $identity = Arr::sortRecursive(['domain' => $domain, 'code' => $code, 'source_type' => $sourceType,
            'source_id' => $sourceId, 'source_code' => $sourceCode, 'scope' => $context]);
        $fingerprint = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        OperationalReconciliationIssue::query()->firstOrCreate([
            'run_id' => $run->id, 'fingerprint' => $fingerprint,
        ], [
            'organization_id' => $run->organization_id, 'domain' => $domain, 'issue_code' => $code,
            'severity' => $severity, 'source_type' => $sourceType, 'source_id' => $sourceId,
            'source_code' => $sourceCode, 'expected' => $expected, 'actual' => $actual,
            'context' => $context ?: null,
        ]);
    }

    /** @param array<string,int> $checked @return array<string,mixed> */
    private function metrics(int $organizationId, OperationalReconciliationRun $run, array $checked): array
    {
        $eventStatuses = AccountingEconomicEvent::query()->where('organization_id', $organizationId)
            ->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status')->map(fn ($value) => (int) $value)->all();
        $oldestPending = AccountingEconomicEvent::query()->where('organization_id', $organizationId)
            ->whereIn('status', ['pending', 'error', 'processing'])->min('occurred_at');

        return [
            'checked' => $checked,
            'issues_by_domain' => $run->issues()->selectRaw('domain, COUNT(*) AS aggregate')->groupBy('domain')->pluck('aggregate', 'domain')->map(fn ($value) => (int) $value)->all(),
            'economic_events_by_status' => $eventStatuses,
            'oldest_unprocessed_event_at' => $oldestPending,
        ];
    }
}
