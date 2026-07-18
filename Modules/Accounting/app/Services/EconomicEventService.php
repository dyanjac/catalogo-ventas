<?php

namespace Modules\Accounting\Services;

use App\Models\Organization;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\EconomicEventStatus;
use Modules\Accounting\Enums\EconomicEventType;
use Modules\Accounting\Exceptions\EconomicEventConflictException;
use Modules\Accounting\Jobs\ProcessEconomicEventJob;
use Modules\Accounting\Models\AccountingAccount;
use Modules\Accounting\Models\AccountingEconomicEvent;
use Modules\Accounting\Models\AccountingEntry;
use Modules\Accounting\Models\AccountingPeriod;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Billing\Models\BillingDocument;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Entities\Product;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Orders\Entities\Order;
use Throwable;

class EconomicEventService
{
    public function __construct(
        private readonly OrganizationEntitlementService $entitlements,
        private readonly ProductAccountingConfigurationResolver $productAccounting,
    ) {}

    public function recordInvoice(Order $order, BillingDocument $document, ?int $actorId = null): AccountingEconomicEvent
    {
        $order->loadMissing('items');

        return $this->record(
            (int) $order->organization_id,
            EconomicEventType::InvoiceIssued,
            "billing-document:{$document->id}:invoice-issued",
            BillingDocument::class,
            (int) $document->id,
            $document->series.'-'.$document->number,
            [
                'order_id' => (int) $order->id,
                'document_type' => (string) $document->document_type,
                'series' => (string) $document->series,
                'number' => (string) $document->number,
                'currency' => (string) $document->currency,
                'total' => (string) $document->total,
                'items' => $order->items->map(fn ($item) => [
                    'product_id' => (int) $item->product_id,
                    'line_total' => (string) $item->line_total,
                    'tax_amount' => (string) $item->tax_amount,
                ])->values()->all(),
            ],
            $document->issued_at ?? $document->issue_date ?? now(),
            $actorId,
            $order->branch_id ? (int) $order->branch_id : null,
        );
    }

    public function recordOrderSale(Order $order, ?int $actorId = null): AccountingEconomicEvent
    {
        $order->loadMissing('items');

        return $this->record(
            (int) $order->organization_id,
            EconomicEventType::InvoiceIssued,
            "order:{$order->id}:sale-issued",
            Order::class,
            (int) $order->id,
            'VENTA-ORDER-'.$order->id,
            [
                'order_id' => (int) $order->id,
                'document_type' => 'order',
                'currency' => (string) $order->currency,
                'total' => (string) $order->total,
                'items' => $order->items->map(fn ($item) => [
                    'product_id' => (int) $item->product_id,
                    'line_total' => (string) $item->line_total,
                    'tax_amount' => (string) $item->tax_amount,
                ])->values()->all(),
            ],
            $order->created_at ?? now(),
            $actorId,
            $order->branch_id ? (int) $order->branch_id : null,
        );
    }

    public function recordCreditNote(Order $order, BillingDocument $document, ?int $actorId = null): AccountingEconomicEvent
    {
        return $this->record(
            (int) $order->organization_id,
            EconomicEventType::CreditNoteIssued,
            "billing-document:{$document->id}:credit-note-issued",
            BillingDocument::class,
            (int) $document->id,
            $document->series.'-'.$document->number,
            [
                'order_id' => (int) $order->id,
                'original_document_id' => (int) $document->related_document_id,
                'document_type' => (string) $document->document_type,
                'series' => (string) $document->series,
                'number' => (string) $document->number,
                'total' => (string) $document->total,
            ],
            $document->issued_at ?? $document->issue_date ?? now(),
            $actorId,
            $order->branch_id ? (int) $order->branch_id : null,
        );
    }

    public function recordPayment(Order $order, ?int $actorId = null): AccountingEconomicEvent
    {
        $order->loadMissing('items');
        $paymentReference = filled($order->transaction_id) ? (string) $order->transaction_id : 'order-'.$order->id;

        return $this->record(
            (int) $order->organization_id,
            EconomicEventType::PaymentReceived,
            "order:{$order->id}:payment:{$paymentReference}",
            Order::class,
            (int) $order->id,
            $paymentReference,
            [
                'order_id' => (int) $order->id,
                'amount' => (string) $order->total,
                'currency' => (string) $order->currency,
                'payment_method' => (string) $order->payment_method,
                'transaction_id' => $order->transaction_id,
                'product_ids' => $order->items->pluck('product_id')->map(fn ($id) => (int) $id)->values()->all(),
            ],
            $order->paid_at ?? now(),
            $actorId,
            $order->branch_id ? (int) $order->branch_id : null,
        );
    }

    public function recordDispatch(Order $order, InventoryDocument $document, ?int $actorId = null): AccountingEconomicEvent
    {
        return $this->recordInventoryCostEvent($order, $document, EconomicEventType::InventoryDispatched, 'dispatch', $actorId);
    }

    public function recordReturn(Order $order, InventoryDocument $document, ?int $actorId = null): AccountingEconomicEvent
    {
        return $this->recordInventoryCostEvent($order, $document, EconomicEventType::InventoryReturned, 'return', $actorId);
    }

    public function reverse(AccountingEconomicEvent $original, string $idempotencyKey, ?int $actorId = null): AccountingEconomicEvent
    {
        if (! in_array($original->status, [EconomicEventStatus::Processed, EconomicEventStatus::Reversed], true) || ! $original->processed_entry_id) {
            throw new DomainException('Solo se puede revertir un evento procesado.');
        }

        return $this->record(
            (int) $original->organization_id,
            EconomicEventType::EntryReversal,
            $idempotencyKey,
            AccountingEconomicEvent::class,
            (int) $original->id,
            'REV-'.$original->id,
            ['original_event_id' => (int) $original->id],
            now(),
            $actorId,
            $original->branch_id,
            (int) $original->id,
        );
    }

    /** @param array<string,mixed> $payload */
    public function record(
        int $organizationId,
        EconomicEventType $type,
        string $idempotencyKey,
        string $sourceType,
        int $sourceId,
        ?string $sourceCode,
        array $payload,
        mixed $occurredAt = null,
        ?int $actorId = null,
        ?int $branchId = null,
        ?int $reversalOfEventId = null,
        bool $autoProcess = true,
    ): AccountingEconomicEvent {
        $normalized = $this->normalize($payload);
        $hash = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        $key = trim($idempotencyKey);
        if ($key === '') {
            throw new DomainException('La clave idempotente del evento es obligatoria.');
        }

        try {
            $event = DB::transaction(function () use ($organizationId, $type, $key, $hash, $sourceType, $sourceId, $sourceCode, $normalized, $occurredAt, $actorId, $branchId, $reversalOfEventId): AccountingEconomicEvent {
                $existing = AccountingEconomicEvent::query()
                    ->where('organization_id', $organizationId)
                    ->where(function ($query) use ($key, $type, $sourceType, $sourceId): void {
                        $query->where('idempotency_key', $key)
                            ->orWhere(fn ($source) => $source->where('event_type', $type->value)->where('source_type', $sourceType)->where('source_id', $sourceId));
                    })
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $this->assertReplay($existing, $hash, $type, $sourceType, $sourceId);
                }

                return AccountingEconomicEvent::query()->create([
                    'organization_id' => $organizationId,
                    'branch_id' => $branchId,
                    'event_type' => $type,
                    'status' => EconomicEventStatus::Pending,
                    'idempotency_key' => $key,
                    'payload_hash' => $hash,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'source_code' => $sourceCode,
                    'payload' => $normalized,
                    'occurred_at' => $occurredAt ?? now(),
                    'created_by' => $actorId,
                    'reversal_of_event_id' => $reversalOfEventId,
                ]);
            });
        } catch (QueryException $exception) {
            $existing = AccountingEconomicEvent::query()->where('organization_id', $organizationId)->where('idempotency_key', $key)->first();
            if (! $existing) {
                throw $exception;
            }
            $event = $this->assertReplay($existing, $hash, $type, $sourceType, $sourceId);
        }

        if ($autoProcess && $event->wasRecentlyCreated && $this->shouldAutoProcess($event)) {
            ProcessEconomicEventJob::dispatch((int) $event->organization_id, (int) $event->id);
        }

        return $event->fresh() ?? $event;
    }

    /**
     * Valida y calcula un asiento sin persistir evento, asiento ni trabajo en cola.
     *
     * @param array<string,mixed> $payload
     * @return array{lines:array<int,array<string,mixed>>,configuration_snapshot:array<string,mixed>,total_debit:float,total_credit:float}
     */
    public function preview(
        int $organizationId,
        EconomicEventType $type,
        string $sourceType,
        int $sourceId,
        ?string $sourceCode,
        array $payload,
        mixed $occurredAt,
        ?int $branchId = null,
    ): array {
        $event = new AccountingEconomicEvent([
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'event_type' => $type,
            'status' => EconomicEventStatus::Pending,
            'idempotency_key' => 'preview:'.$type->value.':'.$sourceType.':'.$sourceId,
            'payload_hash' => str_repeat('0', 64),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_code' => $sourceCode,
            'payload' => $this->normalize($payload),
            'occurred_at' => $occurredAt,
        ]);

        $this->assertPostable($event, true);
        [$lines, $snapshot] = $this->buildLines($event);
        if ($lines === []) {
            throw new DomainException('El evento no produjo líneas contables configuradas.');
        }
        $debit = round(array_sum(array_column($lines, 'debit')), 2);
        $credit = round(array_sum(array_column($lines, 'credit')), 2);
        if ($debit <= 0 || abs($debit - $credit) > 0.0001) {
            throw new DomainException('El asiento económico no cuadra en partida doble.');
        }

        return [
            'lines' => $lines,
            'configuration_snapshot' => $snapshot,
            'total_debit' => $debit,
            'total_credit' => $credit,
        ];
    }

    public function retry(int $organizationId, int $eventId): AccountingEconomicEvent
    {
        $event = AccountingEconomicEvent::query()->where('organization_id', $organizationId)->findOrFail($eventId);
        if ($event->status === EconomicEventStatus::Error || $event->status === EconomicEventStatus::Processing) {
            $event->forceFill(['status' => EconomicEventStatus::Pending, 'next_retry_at' => null])->save();
        }
        ProcessEconomicEventJob::dispatch($organizationId, $eventId);

        return $event->fresh();
    }

    public function process(int $organizationId, int $eventId): ?AccountingEntry
    {
        $event = DB::transaction(function () use ($organizationId, $eventId): ?AccountingEconomicEvent {
            $locked = AccountingEconomicEvent::query()->where('organization_id', $organizationId)->lockForUpdate()->findOrFail($eventId);
            if (in_array($locked->status, [EconomicEventStatus::Processed, EconomicEventStatus::Reversed], true)) {
                return null;
            }
            if ($locked->status === EconomicEventStatus::Processing) {
                return null;
            }
            $locked->forceFill([
                'status' => EconomicEventStatus::Processing,
                'attempts' => $locked->attempts + 1,
                'error_code' => null,
                'error_message' => null,
            ])->save();

            return $locked;
        });

        if (! $event) {
            return AccountingEconomicEvent::query()->where('organization_id', $organizationId)->find($eventId)?->entry;
        }

        try {
            return DB::transaction(fn () => $this->postLocked($organizationId, $eventId), 3);
        } catch (Throwable $exception) {
            DB::transaction(function () use ($organizationId, $eventId, $exception): void {
                $failed = AccountingEconomicEvent::query()->where('organization_id', $organizationId)->lockForUpdate()->find($eventId);
                if ($failed && $failed->status === EconomicEventStatus::Processing) {
                    $failed->forceFill([
                        'status' => EconomicEventStatus::Error,
                        'error_code' => class_basename($exception),
                        'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                        'next_retry_at' => now()->addMinutes(5),
                    ])->save();
                }
            });

            return null;
        }
    }

    private function postLocked(int $organizationId, int $eventId): AccountingEntry
    {
        $event = AccountingEconomicEvent::query()->where('organization_id', $organizationId)->lockForUpdate()->findOrFail($eventId);
        if ($event->status !== EconomicEventStatus::Processing) {
            throw new DomainException('El evento no está reclamado para procesamiento.');
        }
        $organization = Organization::query()->findOrFail($organizationId);
        if ($organization->isSuspended()) {
            throw new DomainException('La organización está suspendida.');
        }
        if (! $this->entitlements->hasCapability('accounting.general_ledger', $organization)) {
            throw new DomainException('La organización no tiene habilitada la contabilidad general.');
        }

        $date = $event->occurred_at ?? now();
        $period = AccountingPeriod::query()->where('organization_id', $organizationId)->where('year', $date->year)->where('month', $date->month)->first();
        if ($period?->status === 'closed') {
            throw new DomainException('El periodo contable del evento está cerrado.');
        }

        [$lines, $snapshot] = $this->buildLines($event);
        if ($lines === []) {
            throw new DomainException('El evento no produjo líneas contables configuradas.');
        }
        $debit = round(array_sum(array_column($lines, 'debit')), 2);
        $credit = round(array_sum(array_column($lines, 'credit')), 2);
        if ($debit <= 0 || abs($debit - $credit) > 0.0001) {
            throw new DomainException('El asiento económico no cuadra en partida doble.');
        }

        $payload = $event->payload;
        $entry = AccountingEntry::query()->create([
            'organization_id' => $organizationId,
            'economic_event_id' => $event->id,
            'origin' => 'economic_event',
            'reversal_of_id' => $event->event_type === EconomicEventType::EntryReversal ? $event->reversalOf?->processed_entry_id : null,
            'payload_hash' => $event->payload_hash,
            'entry_date' => $date->toDateString(),
            'period_year' => $date->year,
            'period_month' => $date->month,
            'voucher_type' => $payload['document_type'] ?? $event->event_type->value,
            'voucher_series' => $payload['series'] ?? null,
            'voucher_number' => isset($payload['number']) ? (string) $payload['number'] : null,
            'reference' => $event->source_code ?? strtoupper($event->event_type->value).'-'.$event->source_id,
            'description' => $event->event_type->label().' · '.$event->source_code,
            'status' => 'posting',
            'total_debit' => $debit,
            'total_credit' => $credit,
            'posted_at' => now(),
            'created_by' => $event->created_by,
        ]);
        $entry->lines()->createMany(array_map(fn (array $line) => [
            ...$line,
            'organization_id' => $organizationId,
            'order_id' => $payload['order_id'] ?? null,
        ], $lines));

        $entry->forceFill([
            'status' => 'posted',
            'posted_at' => now(),
        ])->save();

        $event->forceFill([
            'configuration_snapshot' => $snapshot,
            'processed_entry_id' => $entry->id,
            'status' => EconomicEventStatus::Processed,
            'processed_at' => now(),
            'next_retry_at' => null,
        ])->save();

        if ($event->event_type === EconomicEventType::EntryReversal && $event->reversalOf) {
            $event->reversalOf->forceFill(['status' => EconomicEventStatus::Reversed])->save();
        }

        return $entry->fresh('lines');
    }

    private function assertPostable(AccountingEconomicEvent $event, bool $requireExistingOpenPeriod = false): void
    {
        $organization = Organization::query()->findOrFail($event->organization_id);
        if ($organization->isSuspended()) {
            throw new DomainException('La organización está suspendida.');
        }
        if (! $this->entitlements->hasCapability('accounting.general_ledger', $organization)) {
            throw new DomainException('La organización no tiene habilitada la contabilidad general.');
        }

        $date = $event->occurred_at;
        if (! $date) {
            throw new DomainException('La fecha económica del evento es obligatoria.');
        }
        $period = AccountingPeriod::query()
            ->where('organization_id', $event->organization_id)
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->first();
        if ($requireExistingOpenPeriod && ! $period) {
            throw new DomainException('No existe un periodo contable abierto para la fecha económica.');
        }
        if ($period?->status === 'closed') {
            throw new DomainException('El periodo contable del evento está cerrado.');
        }
    }

    /** @return array{array<int,array<string,mixed>>,array<string,mixed>} */
    private function buildLines(AccountingEconomicEvent $event): array
    {
        return match ($event->event_type) {
            EconomicEventType::InvoiceIssued => $this->buildInvoiceLines($event),
            EconomicEventType::InventoryDispatched => $this->buildInventoryLines($event, false),
            EconomicEventType::PaymentReceived => $this->buildPaymentLines($event),
            EconomicEventType::CreditNoteIssued => $this->buildCreditNoteLines($event),
            EconomicEventType::InventoryReturned => $this->buildInventoryLines($event, true),
            EconomicEventType::ServiceAccrued => $this->buildServiceAccrualLines($event),
            EconomicEventType::SubscriptionDeferred => $this->buildSubscriptionDeferredLines($event),
            EconomicEventType::EntryReversal => $this->buildReversalLines($event),
        };
    }

    private function buildInvoiceLines(AccountingEconomicEvent $event): array
    {
        $lines = [];
        $snapshot = ['products' => []];
        $receivable = null;
        foreach ($event->payload['items'] ?? [] as $item) {
            $product = $this->product($event, (int) $item['product_id']);
            $config = $this->productAccounting->resolve($product);
            $snapshot['products'][$product->id] = ['treatment' => $config->treatment->value, 'accounts' => $config->accounts, 'sources' => $config->accountSources];
            if ($config->treatment === ProductAccountingTreatment::NotApplicable) {
                continue;
            }
            if (! $config->isAutomatic()) {
                throw new DomainException("El producto {$product->id} no tiene tratamiento contable automático.");
            }
            $receivable ??= $this->account($event, 'receivable', $config->account('receivable'));
            $total = round((float) $item['line_total'], 2);
            $tax = round((float) $item['tax_amount'], 2);
            $revenue = round($total - $tax, 2);
            if ($revenue > 0) {
                $this->addLine($lines, $this->account($event, 'revenue', $config->account('revenue')), 0, $revenue, 'Ingreso por venta', $product->id);
            }
            if ($tax > 0) {
                $this->addLine($lines, $this->account($event, 'tax', $config->account('tax')), 0, $tax, 'IGV por pagar', $product->id);
            }
        }
        $credit = round(array_sum(array_column($lines, 'credit')), 2);
        if ($credit > 0 && $receivable) {
            $this->addLine($lines, $receivable, $credit, 0, 'Cuenta por cobrar');
        }

        return [array_values($lines), $snapshot];
    }

    /** @return array{array<int,array<string,mixed>>,array<string,mixed>} */
    private function buildServiceAccrualLines(AccountingEconomicEvent $event): array
    {
        $product = $this->product($event, (int) ($event->payload['product_id'] ?? 0));
        $accounts = (array) ($event->payload['accounts'] ?? []);
        $amount = round(((int) ($event->payload['amount_minor'] ?? 0)) / 100, 2);
        if ($amount === 0.0) {
            throw new DomainException('El devengamiento debe tener un importe distinto de cero.');
        }
        $deferred = $this->account($event, 'deferred_revenue', $accounts['deferred_revenue'] ?? null);
        $revenue = $this->account($event, 'revenue', $accounts['revenue'] ?? null);
        $lines = [];
        if ($amount > 0) {
            $this->addLine($lines, $deferred, $amount, 0, 'LiberaciÃ³n de ingreso diferido', $product->id);
            $this->addLine($lines, $revenue, 0, $amount, 'Ingreso por suscripciÃ³n devengado', $product->id);
        } else {
            $value = abs($amount);
            $this->addLine($lines, $revenue, $value, 0, 'Ajuste de ingreso por suscripciÃ³n', $product->id);
            $this->addLine($lines, $deferred, 0, $value, 'ReposiciÃ³n de ingreso diferido', $product->id);
        }

        return [$lines, [
            'product_id' => $product->id,
            'accounts' => $accounts,
        ]];
    }

    /** @return array{array<int,array<string,mixed>>,array<string,mixed>} */
    private function buildSubscriptionDeferredLines(AccountingEconomicEvent $event): array
    {
        $invoiceEvent = AccountingEconomicEvent::query()->where('organization_id', $event->organization_id)
            ->find((int) ($event->payload['invoice_event_id'] ?? 0));
        if (! $invoiceEvent || $invoiceEvent->status !== EconomicEventStatus::Processed) {
            throw new DomainException('La factura debe contabilizarse antes de reclasificar su ingreso como diferido.');
        }
        $product = $this->product($event, (int) ($event->payload['product_id'] ?? 0));
        $accounts = (array) ($event->payload['accounts'] ?? []);
        $amount = round(((int) ($event->payload['amount_minor'] ?? 0)) / 100, 2);
        if ($amount <= 0) {
            throw new DomainException('El ingreso a diferir debe ser positivo.');
        }
        $revenue = $this->account($event, 'revenue', $accounts['revenue'] ?? null);
        $deferred = $this->account($event, 'deferred_revenue', $accounts['deferred_revenue'] ?? null);
        $lines = [];
        $this->addLine($lines, $revenue, $amount, 0, 'ReclasificaciÃ³n de ingreso anticipado', $product->id);
        $this->addLine($lines, $deferred, 0, $amount, 'Ingreso diferido por suscripciÃ³n', $product->id);

        return [$lines, ['product_id' => $product->id, 'accounts' => $accounts]];
    }

    private function buildInventoryLines(AccountingEconomicEvent $event, bool $return): array
    {
        $lines = [];
        $snapshot = ['products' => []];
        foreach ($event->payload['items'] ?? [] as $item) {
            $cost = round((float) $item['total_cost'], 2);
            if ($cost <= 0) {
                continue;
            }
            $product = $this->product($event, (int) $item['product_id']);
            $config = $this->productAccounting->resolve($product);
            $snapshot['products'][$product->id] = ['treatment' => $config->treatment->value, 'accounts' => $config->accounts, 'sources' => $config->accountSources];
            if ($config->treatment === ProductAccountingTreatment::NotApplicable) {
                continue;
            }
            if (! $config->isAutomatic()) {
                throw new DomainException("El producto {$product->id} no tiene tratamiento contable automático.");
            }
            $inventory = $this->account($event, 'inventory', $config->account('inventory'));
            $cogs = $this->account($event, 'cogs', $config->account('cogs'));
            if ($return) {
                $this->addLine($lines, $inventory, $cost, 0, 'Reingreso de inventario', $product->id);
                $this->addLine($lines, $cogs, 0, $cost, 'Reversión del costo de venta', $product->id);
            } else {
                $this->addLine($lines, $cogs, $cost, 0, 'Costo de venta', $product->id);
                $this->addLine($lines, $inventory, 0, $cost, 'Salida de inventario', $product->id);
            }
        }

        return [array_values($lines), $snapshot];
    }

    private function buildPaymentLines(AccountingEconomicEvent $event): array
    {
        $amount = round((float) ($event->payload['amount'] ?? 0), 2);
        $settings = AccountingSetting::query()->where('organization_id', $event->organization_id)->first();
        $receivableCode = $settings?->default_account_receivable;
        if (! $receivableCode) {
            foreach ($event->payload['product_ids'] ?? [] as $productId) {
                $config = $this->productAccounting->resolve($this->product($event, (int) $productId));
                if ($config->isAutomatic() && $config->account('receivable')) {
                    $receivableCode = $config->account('receivable');
                    break;
                }
            }
        }
        $cash = $this->account($event, 'cash', $settings?->default_account_cash);
        $receivable = $this->account($event, 'receivable', $receivableCode);

        return [[
            $this->line($cash, $amount, 0, 'Ingreso de caja/bancos'),
            $this->line($receivable, 0, $amount, 'Cancelación de cuenta por cobrar'),
        ], ['accounts' => ['cash' => $cash->code, 'receivable' => $receivable->code]]];
    }

    private function buildCreditNoteLines(AccountingEconomicEvent $event): array
    {
        $original = AccountingEconomicEvent::query()
            ->where('organization_id', $event->organization_id)
            ->where('event_type', EconomicEventType::InvoiceIssued->value)
            ->where('source_type', BillingDocument::class)
            ->where('source_id', (int) ($event->payload['original_document_id'] ?? 0))
            ->first();
        if (! $original?->processed_entry_id) {
            throw new DomainException('La nota de crédito requiere el asiento del comprobante original.');
        }
        $original->load('entry.lines');
        $originalTotal = round((float) $original->entry->total_debit, 2);
        $creditTotal = round((float) ($event->payload['total'] ?? 0), 2);
        if ($originalTotal <= 0 || $creditTotal <= 0 || $creditTotal > $originalTotal) {
            throw new DomainException('El importe de la nota de crédito no es válido para el asiento original.');
        }
        $ratio = $creditTotal / $originalTotal;
        $lines = [];
        foreach ($original->entry->lines as $source) {
            $this->addLine(
                $lines,
                (object) ['code' => $source->account_code, 'name' => $source->account_name],
                round((float) $source->credit * $ratio, 2),
                round((float) $source->debit * $ratio, 2),
                'Nota de crédito · '.$source->line_description,
                $source->product_id,
            );
        }
        $this->correctRounding($lines);

        return [array_values($lines), ['original_event_id' => $original->id, 'ratio' => $ratio, 'original_payload_hash' => $original->payload_hash]];
    }

    private function buildReversalLines(AccountingEconomicEvent $event): array
    {
        $original = $event->reversalOf()->with('entry.lines')->first();
        if (! $original?->entry) {
            throw new DomainException('No existe un asiento original procesado para revertir.');
        }
        $lines = $original->entry->lines->map(fn ($source) => [
            'account_code' => $source->account_code,
            'account_name' => $source->account_name,
            'debit' => round((float) $source->credit, 2),
            'credit' => round((float) $source->debit, 2),
            'line_description' => 'Reversión · '.$source->line_description,
            'product_id' => $source->product_id,
        ])->all();

        return [$lines, ['original_event_id' => $original->id, 'original_entry_id' => $original->entry->id, 'original_payload_hash' => $original->payload_hash]];
    }

    private function recordInventoryCostEvent(Order $order, InventoryDocument $document, EconomicEventType $type, string $suffix, ?int $actorId): AccountingEconomicEvent
    {
        $document->loadMissing('items.movement');
        $items = $document->items->map(fn ($item) => [
            'product_id' => (int) $item->product_id,
            'movement_id' => $item->inventory_movement_id ? (int) $item->inventory_movement_id : null,
            'quantity' => (int) $item->quantity,
            'total_cost' => (string) ($item->movement?->total_cost ?? $item->line_total ?? 0),
        ])->values()->all();

        return $this->record(
            (int) $order->organization_id,
            $type,
            "inventory-document:{$document->id}:{$suffix}",
            InventoryDocument::class,
            (int) $document->id,
            (string) $document->code,
            ['order_id' => (int) $order->id, 'document_id' => (int) $document->id, 'items' => $items],
            $document->confirmed_at ?? now(),
            $actorId,
            $order->branch_id ? (int) $order->branch_id : null,
        );
    }

    private function account(AccountingEconomicEvent $event, string $role, ?string $code): AccountingAccount
    {
        $settings = AccountingSetting::query()->where('organization_id', $event->organization_id)->first();
        $code = trim((string) ($code ?: $settings?->{'default_account_'.$role}));
        $query = AccountingAccount::query()->where('organization_id', $event->organization_id)->where('is_active', true);
        if ($code !== '') {
            $account = (clone $query)->where('code', $code)->first();
            if (! $account) {
                throw new DomainException("La cuenta {$code} configurada para {$role} no existe o está inactiva.");
            }

            return $account;
        }
        if ($role === 'deferred_revenue') {
            throw new DomainException('La cuenta de ingresos diferidos debe configurarse explÃ­citamente.');
        }
        $account = match ($role) {
            'revenue' => (clone $query)->where('is_default_sales', true)->first(),
            'receivable' => (clone $query)->where('is_default_receivable', true)->first(),
            'tax' => (clone $query)->where('is_default_tax', true)->first(),
            default => null,
        };
        $account ??= (clone $query)->where('type', match ($role) {
            'revenue' => 'ingreso', 'tax', 'deferred_revenue' => 'pasivo', 'cogs' => 'gasto', default => 'activo',
        })->orderBy('code')->first();
        if (! $account) {
            throw new DomainException("No existe una cuenta activa para {$role}.");
        }

        return $account;
    }

    private function product(AccountingEconomicEvent $event, int $productId): Product
    {
        return Product::withTrashed()->where('organization_id', $event->organization_id)->findOrFail($productId);
    }

    private function addLine(array &$lines, object $account, float $debit, float $credit, string $description, ?int $productId = null): void
    {
        $key = $account->code.'|'.$description.'|'.($productId ?? 0);
        if (! isset($lines[$key])) {
            $lines[$key] = $this->line($account, 0, 0, $description, $productId);
        }
        $lines[$key]['debit'] = round($lines[$key]['debit'] + $debit, 2);
        $lines[$key]['credit'] = round($lines[$key]['credit'] + $credit, 2);
    }

    private function line(object $account, float $debit, float $credit, string $description, ?int $productId = null): array
    {
        return ['account_code' => $account->code, 'account_name' => $account->name, 'debit' => $debit, 'credit' => $credit, 'line_description' => $description, 'product_id' => $productId];
    }

    private function correctRounding(array &$lines): void
    {
        $difference = round(array_sum(array_column($lines, 'debit')) - array_sum(array_column($lines, 'credit')), 2);
        if (abs($difference) <= 0.01 && $difference !== 0.0) {
            $key = array_key_last($lines);
            if ($difference > 0) {
                $lines[$key]['credit'] = round($lines[$key]['credit'] + $difference, 2);
            } else {
                $lines[$key]['debit'] = round($lines[$key]['debit'] + abs($difference), 2);
            }
        }
    }

    private function assertReplay(AccountingEconomicEvent $event, string $hash, EconomicEventType $type, string $sourceType, int $sourceId): AccountingEconomicEvent
    {
        if ($event->payload_hash !== $hash || $event->event_type !== $type || $event->source_type !== $sourceType || (int) $event->source_id !== $sourceId) {
            throw new EconomicEventConflictException('La clave idempotente o fuente ya fue usada con un payload diferente.');
        }

        return $event;
    }

    private function shouldAutoProcess(AccountingEconomicEvent $event): bool
    {
        $organization = Organization::query()->find($event->organization_id);
        $settings = AccountingSetting::query()->where('organization_id', $event->organization_id)->first();

        return $organization && ! $organization->isSuspended()
            && $this->entitlements->hasCapability('accounting.general_ledger', $organization)
            && (bool) $settings?->auto_post_entries;
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}
