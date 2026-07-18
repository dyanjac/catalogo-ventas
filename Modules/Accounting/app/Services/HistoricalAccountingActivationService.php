<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use App\Models\Organization;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Enums\EconomicEventStatus;
use Modules\Accounting\Enums\EconomicEventType;
use Modules\Accounting\Models\AccountingActivationItem;
use Modules\Accounting\Models\AccountingActivationRun;
use Modules\Accounting\Models\AccountingAccount;
use Modules\Accounting\Models\AccountingEconomicEvent;
use Modules\Accounting\Models\AccountingEntry;
use Modules\Accounting\Models\AccountingPeriod;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Billing\Models\BillingDocument;
use Modules\Catalog\Entities\InventoryDocument;
use Modules\Catalog\Enums\InventoryDocumentStatus;
use Modules\Catalog\Enums\InventoryDocumentType;
use Modules\Orders\Entities\Order;
use Throwable;

final class HistoricalAccountingActivationService
{
    public function __construct(private readonly EconomicEventService $economicEvents) {}

    public function simulate(int $organizationId, string $cutoffDate, ?int $actorId = null): AccountingActivationRun
    {
        $cutoff = CarbonImmutable::parse($cutoffDate, 'UTC')->startOfDay();
        $through = CarbonImmutable::now('UTC');
        if ($cutoff->isAfter($through)) {
            throw new DomainException('La fecha de corte no puede estar en el futuro.');
        }

        return DB::transaction(function () use ($organizationId, $cutoff, $through, $actorId): AccountingActivationRun {
            $configuration = $this->configurationSnapshot($organizationId, $cutoff, $through);
            $configurationIssues = $this->configurationIssues($organizationId);
            $run = AccountingActivationRun::query()->create([
                'organization_id' => $organizationId,
                'status' => 'simulating',
                'cutoff_at' => $cutoff,
                'captured_through_at' => $through,
                'simulation_hash' => str_repeat('0', 64),
                'confirmation_token' => Str::upper(Str::random(10)),
                'configuration_snapshot' => $configuration,
                'summary' => [],
                'created_by' => $actorId,
            ]);

            foreach ($this->definitions($organizationId, $cutoff, $through) as $definition) {
                $this->materialize($run, $definition);
            }

            $items = $run->items()->orderBy('dependency_order')->orderBy('occurred_at')->orderBy('id')->get();
            $summary = $items->groupBy('event_type')->map(fn (Collection $rows) => [
                'total' => $rows->count(),
                'eligible' => $rows->where('status', 'eligible')->count(),
                'already_present' => $rows->where('status', 'already_present')->count(),
                'inconsistent' => $rows->where('status', 'inconsistent')->count(),
            ])->all();
            $summary['_run_issues'] = $configurationIssues;
            $hash = $this->runHash($run, $configuration, $items);
            $errors = $items->where('status', 'inconsistent')->count() + count($configurationIssues);
            $run->forceFill([
                'status' => $errors > 0 ? 'blocked' : 'simulated',
                'simulation_hash' => $hash,
                'summary' => $summary,
                'eligible_count' => $items->where('status', 'eligible')->count(),
                'existing_count' => $items->where('status', 'already_present')->count(),
                'error_count' => $errors,
            ])->save();

            return $run->fresh('items');
        }, 3);
    }

    public function confirm(AccountingActivationRun $run, string $confirmation, string $expectedHash, ?int $actorId): AccountingActivationRun
    {
        $expectedPhrase = 'CONFIRMAR '.$run->confirmation_token;
        if (! hash_equals($expectedPhrase, trim($confirmation))) {
            throw new DomainException('La frase de confirmación no coincide.');
        }
        if (! hash_equals($run->simulation_hash, trim($expectedHash))) {
            throw new DomainException('El hash aprobado no coincide con la simulación.');
        }

        return DB::transaction(function () use ($run, $actorId): AccountingActivationRun {
            Organization::query()->lockForUpdate()->findOrFail($run->organization_id);
            $locked = AccountingActivationRun::query()
                ->where('organization_id', $run->organization_id)
                ->lockForUpdate()
                ->findOrFail($run->id);
            if ($locked->status === 'completed') {
                return $locked;
            }
            if ($locked->status !== 'simulated' || $locked->error_count > 0) {
                throw new DomainException('Solo una simulación vigente y sin errores puede confirmarse.');
            }
            if ($locked->items()->where('status', 'inconsistent')->exists()
                || count((array) data_get($locked->summary, '_run_issues', [])) > 0) {
                throw new DomainException('La simulación conserva inconsistencias bloqueantes.');
            }
            if (AccountingActivationRun::query()->where('organization_id', $locked->organization_id)
                ->whereIn('status', ['confirmed', 'processing'])->where('id', '<>', $locked->id)->exists()) {
                throw new DomainException('Ya existe otra activación histórica en proceso para la organización.');
            }
            $this->assertSnapshotCurrent($locked);
            $locked->forceFill([
                'status' => 'confirmed', 'confirmed_by' => $actorId,
                'confirmed_at' => now('UTC'), 'error_code' => null, 'error_message' => null,
            ])->save();

            return $locked;
        }, 3);
    }

    public function process(AccountingActivationRun $run): AccountingActivationRun
    {
        try {
            DB::transaction(function () use ($run): void {
                $locked = AccountingActivationRun::query()->where('organization_id', $run->organization_id)
                    ->lockForUpdate()->findOrFail($run->id);
                if ($locked->status === 'completed') {
                    return;
                }
                if (! in_array($locked->status, ['confirmed', 'failed'], true)) {
                    throw new DomainException('La corrida no está confirmada para procesamiento.');
                }
                $this->assertSnapshotCurrent($locked);
                $locked->forceFill(['status' => 'processing', 'started_at' => $locked->started_at ?? now('UTC'), 'error_code' => null, 'error_message' => null])->save();

                $items = $locked->items()->where('status', 'eligible')
                    ->orderBy('dependency_order')->orderBy('occurred_at')->orderBy('id')->lockForUpdate()->get();
                foreach ($items as $item) {
                    $definition = $this->definitionForItem($item);
                    $deferredPreview = $item->event_type === EconomicEventType::CreditNoteIssued->value
                        && data_get($item->configuration_snapshot, 'deferred_validation') === true;
                    $preview = $this->previewDefinition($definition);
                    $currentHash = $this->itemSimulationHash($definition, $deferredPreview ? ['deferred_validation' => true] : $preview);
                    if (! hash_equals($item->simulation_hash, $currentHash)) {
                        throw new DomainException("La fuente {$item->source_code} cambió después de la simulación.");
                    }

                    $event = $this->economicEvents->record(
                        (int) $item->organization_id,
                        EconomicEventType::from($item->event_type),
                        $item->idempotency_key,
                        $item->source_type,
                        (int) $item->source_id,
                        $item->source_code,
                        $item->payload,
                        $item->occurred_at,
                        $locked->confirmed_by,
                        $definition['branch_id'],
                        null,
                        false,
                    );
                    $entry = $this->economicEvents->process((int) $item->organization_id, (int) $event->id);
                    $event = $event->fresh();
                    if (! $entry || $event?->status !== EconomicEventStatus::Processed) {
                        throw new DomainException($event?->error_message ?: "No se pudo procesar {$item->source_code}.");
                    }
                    $item->forceFill([
                        'status' => 'processed', 'accounting_economic_event_id' => $event->id,
                        'accounting_entry_id' => $entry->id, 'processed_at' => now('UTC'),
                    ])->save();
                }

                $processed = $locked->items()->where('status', 'processed')->count();
                $locked->forceFill([
                    'status' => 'completed', 'processed_count' => $processed,
                    'completed_at' => now('UTC'), 'error_code' => null, 'error_message' => null,
                ])->save();
            }, 3);
        } catch (Throwable $exception) {
            AccountingActivationRun::query()->where('organization_id', $run->organization_id)->whereKey($run->id)
                ->whereIn('status', ['confirmed', 'processing', 'failed'])->update([
                    'status' => 'failed', 'error_code' => class_basename($exception),
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000), 'updated_at' => now(),
                ]);
            throw $exception;
        }

        return $run->fresh('items');
    }

    /** @return Collection<int,array<string,mixed>> */
    private function definitions(int $organizationId, CarbonImmutable $cutoff, CarbonImmutable $through): Collection
    {
        $definitions = collect();
        BillingDocument::query()->where('organization_id', $organizationId)->where('status', 'issued')
            ->whereIn('document_type', ['factura', 'boleta'])
            ->where(fn ($query) => $query->whereBetween('issued_at', [$cutoff, $through])
                ->orWhere(fn ($missing) => $missing->whereNull('issued_at')->whereBetween('issue_date', [$cutoff->toDateString(), $through->toDateString()])))
            ->with('order.items.product')->orderBy('issued_at')->orderBy('id')
            ->each(fn (BillingDocument $document) => $definitions->push($this->invoiceDefinition($document)));
        Order::query()->where('organization_id', $organizationId)
            ->whereIn('status', ['fulfilled', 'completed', 'legacy_completed'])
            ->whereBetween('created_at', [$cutoff, $through])
            ->whereDoesntHave('billingDocuments', fn ($query) => $query->where('status', 'issued')->whereIn('document_type', ['factura', 'boleta']))
            ->with('items.product')->orderBy('created_at')->orderBy('id')
            ->each(fn (Order $order) => $definitions->push($this->ambiguousOrderSaleDefinition($order)));
        Order::query()->where('organization_id', $organizationId)->where('payment_status', 'paid')
            ->where(fn ($query) => $query->whereBetween('paid_at', [$cutoff, $through])
                ->orWhere(fn ($missing) => $missing->whereNull('paid_at')->whereBetween('created_at', [$cutoff, $through])))
            ->with(['items.product', 'billingDocuments'])->orderBy('paid_at')->orderBy('id')
            ->each(fn (Order $order) => $definitions->push($this->paymentDefinition($order)));
        Order::query()->where('organization_id', $organizationId)->whereNotNull('dispatch_document_id')
            ->with(['dispatchDocument.items.movement', 'items.product'])->orderBy('id')->get()
            ->filter(fn (Order $order) => $order->dispatchDocument
                && ($order->dispatchDocument->confirmed_at?->betweenIncluded($cutoff, $through)
                    || (! $order->dispatchDocument->confirmed_at && $order->dispatchDocument->created_at?->betweenIncluded($cutoff, $through))))
            ->each(fn (Order $order) => $definitions->push($this->inventoryDefinition($order, $order->dispatchDocument, false)));
        BillingDocument::query()->where('organization_id', $organizationId)->where('status', 'issued')
            ->where('document_type', 'credit_note')
            ->where(fn ($query) => $query->whereBetween('issued_at', [$cutoff, $through])
                ->orWhere(fn ($missing) => $missing->whereNull('issued_at')->whereBetween('issue_date', [$cutoff->toDateString(), $through->toDateString()])))
            ->with(['order.items.product', 'relatedDocument'])->orderBy('issued_at')->orderBy('id')
            ->each(fn (BillingDocument $document) => $definitions->push($this->creditNoteDefinition($document)));
        Order::query()->where('organization_id', $organizationId)->whereNotNull('return_document_id')
            ->with(['returnDocument.items.movement', 'items.product'])->orderBy('id')->get()
            ->filter(fn (Order $order) => $order->returnDocument
                && ($order->returnDocument->confirmed_at?->betweenIncluded($cutoff, $through)
                    || (! $order->returnDocument->confirmed_at && $order->returnDocument->created_at?->betweenIncluded($cutoff, $through))))
            ->each(fn (Order $order) => $definitions->push($this->inventoryDefinition($order, $order->returnDocument, true)));

        return $definitions;
    }

    /** @param array<string,mixed> $definition */
    private function materialize(AccountingActivationRun $run, array $definition): void
    {
        $issues = $definition['issues'];
        $existing = AccountingEconomicEvent::query()->where('organization_id', $run->organization_id)
            ->where('event_type', $definition['type']->value)->where('source_type', $definition['source_type'])
            ->where('source_id', $definition['source_id'])->first();
        $payloadHash = $this->payloadHash($definition['payload']);
        $status = 'eligible';
        $preview = null;

        if ($existing) {
            if (! hash_equals($existing->payload_hash, $payloadHash)) {
                $issues[] = $this->issue('existing_event_conflict', 'Ya existe un evento para la fuente con payload diferente.');
            } elseif ($existing->status === EconomicEventStatus::Processed) {
                $status = 'already_present';
            } else {
                $issues[] = $this->issue('existing_event_unavailable', 'El evento existente no está procesado de forma estable; requiere revisión explícita.');
            }
        }
        if ($this->manualDuplicateExists($run, $definition)) {
            $issues[] = $this->issue('manual_entry_duplicate', 'Existe un asiento manual que podría representar el mismo hecho.');
        }
        if ($definition['dependency_key'] && ! $this->dependencySatisfied($run, $definition['dependency_key'])) {
            $issues[] = $this->issue('missing_accounting_dependency', 'La contrapartida histórica quedó fuera del corte o no es contabilizable; requiere saldo de apertura o corrección.');
        }

        if ($issues === [] && $status === 'eligible' && $definition['type'] === EconomicEventType::CreditNoteIssued
            && $definition['dependency_key'] && ! AccountingEconomicEvent::query()
                ->where('organization_id', $run->organization_id)->where('idempotency_key', $definition['dependency_key'])
                ->where('status', EconomicEventStatus::Processed->value)->exists()) {
            $preview = ['deferred_validation' => true];
        } elseif ($issues === [] && $status === 'eligible') {
            try {
                $preview = $this->previewDefinition($definition);
            } catch (Throwable $exception) {
                $issues[] = $this->issue('posting_preview_failed', $exception->getMessage());
            }
        }
        if ($issues !== []) {
            $status = 'inconsistent';
        }
        $preview ??= ['deferred_validation' => true];

        AccountingActivationItem::query()->create([
            'activation_run_id' => $run->id, 'organization_id' => $run->organization_id,
            'event_type' => $definition['type']->value, 'source_type' => $definition['source_type'],
            'source_id' => $definition['source_id'], 'source_code' => $definition['source_code'],
            'occurred_at' => $definition['occurred_at'], 'idempotency_key' => $definition['idempotency_key'],
            'payload_hash' => $payloadHash, 'simulation_hash' => $this->itemSimulationHash($definition, $preview),
            'status' => $status, 'dependency_order' => $definition['dependency_order'],
            'dependency_key' => $definition['dependency_key'], 'payload' => $definition['payload'],
            'configuration_snapshot' => $preview, 'issues' => $issues ?: null,
            'accounting_economic_event_id' => $status === 'already_present' ? $existing?->id : null,
            'accounting_entry_id' => $status === 'already_present' ? $existing?->processed_entry_id : null,
        ]);
    }

    /** @return array<string,mixed> */
    private function invoiceDefinition(BillingDocument $document): array
    {
        $issues = $this->validateDocumentOrder($document);
        $order = $document->order;
        if ($order) {
            $lineTotal = round((float) $order->items->sum(fn ($item) => (float) $item->line_total), 2);
            $lineTax = round((float) $order->items->sum(fn ($item) => (float) $item->tax_amount), 2);
            if (abs($lineTotal - (float) $document->total) > 0.01 || abs($lineTax - (float) $document->tax) > 0.01) {
                $issues[] = $this->issue('document_totals_mismatch', 'Los importes de las líneas no concilian con el comprobante.');
            }
            if (abs(((float) $document->subtotal + (float) $document->tax) - (float) $document->total) > 0.01) {
                $issues[] = $this->issue('document_header_mismatch', 'Subtotal más impuesto no coincide con el total del comprobante.');
            }
            if (BillingDocument::query()->where('organization_id', $document->organization_id)->where('order_id', $order->id)
                ->where('status', 'issued')->whereIn('document_type', ['factura', 'boleta'])->whereKeyNot($document->id)->exists()) {
                $issues[] = $this->issue('multiple_issued_documents', 'El pedido tiene más de un comprobante de venta emitido.');
            }
        }
        $payload = [
            'order_id' => (int) $document->order_id, 'document_type' => (string) $document->document_type,
            'series' => (string) $document->series, 'number' => (string) $document->number,
            'currency' => (string) $document->currency, 'total' => (string) $document->total,
            'items' => $order?->items->map(fn ($item) => ['product_id' => (int) $item->product_id, 'line_total' => (string) $item->line_total, 'tax_amount' => (string) $item->tax_amount])->values()->all() ?? [],
        ];

        return $this->definition(EconomicEventType::InvoiceIssued, BillingDocument::class, $document->id,
            $document->series.'-'.$document->number, "billing-document:{$document->id}:invoice-issued", $payload,
            $document->issued_at, $document->branch_id, 10, null, $issues);
    }

    /** @return array<string,mixed> */
    private function paymentDefinition(Order $order): array
    {
        $issues = $this->validateOrder($order);
        if (! $order->paid_at) {
            $issues[] = $this->issue('missing_economic_date', 'El pago no tiene paid_at.');
        }
        $invoice = $order->billingDocuments->first(fn ($document) => $document->status === 'issued' && in_array($document->document_type, ['factura', 'boleta'], true));
        if (! $invoice) {
            $issues[] = $this->issue('missing_invoice_dependency', 'El cobro no tiene comprobante emitido reconstruible.');
        }
        $reference = filled($order->transaction_id) ? (string) $order->transaction_id : 'order-'.$order->id;
        $payload = ['order_id' => (int) $order->id, 'amount' => (string) $order->total,
            'currency' => (string) $order->currency, 'payment_method' => (string) $order->payment_method,
            'transaction_id' => $order->transaction_id,
            'product_ids' => $order->items->pluck('product_id')->map(fn ($id) => (int) $id)->values()->all()];

        return $this->definition(EconomicEventType::PaymentReceived, Order::class, $order->id, $reference,
            "order:{$order->id}:payment:{$reference}", $payload, $order->paid_at, $order->branch_id, 20,
            $invoice ? "billing-document:{$invoice->id}:invoice-issued" : null, $issues);
    }

    /** @return array<string,mixed> */
    private function ambiguousOrderSaleDefinition(Order $order): array
    {
        $issues = [...$this->validateOrder($order), $this->issue(
            'ambiguous_order_sale',
            'La venta no tiene comprobante emitido; requiere decisión y saldo de apertura, no se activa automáticamente.',
        )];
        $payload = [
            'order_id' => (int) $order->id, 'document_type' => 'order', 'currency' => (string) $order->currency,
            'total' => (string) $order->total,
            'items' => $order->items->map(fn ($item) => ['product_id' => (int) $item->product_id,
                'line_total' => (string) $item->line_total, 'tax_amount' => (string) $item->tax_amount])->values()->all(),
        ];

        return $this->definition(EconomicEventType::InvoiceIssued, Order::class, $order->id, 'VENTA-ORDER-'.$order->id,
            "order:{$order->id}:sale-issued", $payload, $order->created_at, $order->branch_id, 10, null, $issues);
    }

    /** @return array<string,mixed> */
    private function creditNoteDefinition(BillingDocument $document): array
    {
        $issues = $this->validateDocumentOrder($document);
        if (! $document->relatedDocument || $document->relatedDocument->organization_id !== $document->organization_id
            || ! in_array($document->relatedDocument->document_type, ['factura', 'boleta'], true)) {
            $issues[] = $this->issue('invalid_original_document', 'La nota de crédito no tiene comprobante original válido del tenant.');
        } elseif ((float) $document->total <= 0 || (float) $document->total > (float) $document->relatedDocument->total) {
            $issues[] = $this->issue('invalid_credit_note_amount', 'El importe de la nota de crédito no es válido respecto del comprobante original.');
        }
        $payload = ['order_id' => (int) $document->order_id, 'original_document_id' => (int) $document->related_document_id,
            'document_type' => (string) $document->document_type, 'series' => (string) $document->series,
            'number' => (string) $document->number, 'total' => (string) $document->total];

        return $this->definition(EconomicEventType::CreditNoteIssued, BillingDocument::class, $document->id,
            $document->series.'-'.$document->number, "billing-document:{$document->id}:credit-note-issued", $payload,
            $document->issued_at, $document->branch_id, 30,
            $document->related_document_id ? "billing-document:{$document->related_document_id}:invoice-issued" : null, $issues);
    }

    /** @return array<string,mixed> */
    private function inventoryDefinition(Order $order, InventoryDocument $document, bool $return): array
    {
        $issues = $this->validateOrder($order);
        $expectedType = $return ? InventoryDocumentType::CustomerReturn : InventoryDocumentType::Dispatch;
        if ($document->organization_id !== $order->organization_id || $document->status !== InventoryDocumentStatus::Confirmed || $document->document_type !== $expectedType) {
            $issues[] = $this->issue('invalid_inventory_document', 'El documento de inventario no es una evidencia confirmada válida del tenant.');
        }
        if (! $document->confirmed_at) {
            $issues[] = $this->issue('missing_economic_date', 'El documento de inventario no tiene confirmed_at.');
        }
        $items = $document->items->map(function ($item) use (&$issues, $document): array {
            if ($item->organization_id !== $document->organization_id || ! $item->inventory_movement_id || ! $item->movement
                || $item->movement->organization_id !== $document->organization_id || (float) $item->movement->total_cost <= 0) {
                $issues[] = $this->issue('missing_immutable_cost', 'Un ítem no tiene movimiento y costo histórico inmutables.');
            }

            return ['product_id' => (int) $item->product_id, 'movement_id' => $item->inventory_movement_id ? (int) $item->inventory_movement_id : null,
                'quantity' => (int) $item->quantity, 'total_cost' => (string) ($item->movement?->total_cost ?? 0)];
        })->values()->all();
        $suffix = $return ? 'return' : 'dispatch';
        $type = $return ? EconomicEventType::InventoryReturned : EconomicEventType::InventoryDispatched;
        $dependency = $return && $order->dispatch_document_id ? "inventory-document:{$order->dispatch_document_id}:dispatch" : null;

        return $this->definition($type, InventoryDocument::class, $document->id, (string) $document->code,
            "inventory-document:{$document->id}:{$suffix}", ['order_id' => (int) $order->id, 'document_id' => (int) $document->id, 'items' => $items],
            $document->confirmed_at, $order->branch_id, $return ? 40 : 15, $dependency, $issues);
    }

    /** @return array<int,array{code:string,message:string}> */
    private function validateDocumentOrder(BillingDocument $document): array
    {
        $issues = [];
        if (! $document->issued_at) {
            $issues[] = $this->issue('missing_economic_date', 'El comprobante no tiene issued_at.');
        }
        if (! $document->order || $document->order->organization_id !== $document->organization_id) {
            $issues[] = $this->issue('cross_tenant_or_missing_order', 'El comprobante no tiene un pedido válido del mismo tenant.');
        } else {
            $issues = [...$issues, ...$this->validateOrder($document->order)];
            if (strtoupper((string) $document->currency) !== strtoupper((string) $document->order->currency)) {
                $issues[] = $this->issue('currency_mismatch', 'La moneda del comprobante no coincide con el pedido.');
            }
        }

        return $issues;
    }

    /** @return array<int,array{code:string,message:string}> */
    private function validateOrder(Order $order): array
    {
        $issues = [];
        if ($order->items->isEmpty()) {
            $issues[] = $this->issue('missing_order_items', 'El pedido no tiene líneas reconstruibles.');
        }
        foreach ($order->items as $item) {
            if ((int) $item->organization_id !== (int) $order->organization_id || ! $item->product
                || (int) $item->product->organization_id !== (int) $order->organization_id) {
                $issues[] = $this->issue('cross_tenant_product', 'Una línea o producto no pertenece al tenant del pedido.');
                break;
            }
        }
        $settings = AccountingSetting::query()->where('organization_id', $order->organization_id)->first();
        if ($settings && strtoupper((string) $order->currency) !== strtoupper((string) $settings->default_currency)) {
            $issues[] = $this->issue('unsupported_foreign_currency', 'No existe snapshot de tipo de cambio para la moneda histórica.');
        }

        return $issues;
    }

    /** @param array<string,mixed> $definition @return array<string,mixed> */
    private function previewDefinition(array $definition): array
    {
        return $this->economicEvents->preview((int) $definition['organization_id'], $definition['type'],
            $definition['source_type'], (int) $definition['source_id'], $definition['source_code'],
            $definition['payload'], $definition['occurred_at'], $definition['branch_id']);
    }

    /** @return array<string,mixed> */
    private function definitionForItem(AccountingActivationItem $item): array
    {
        $definition = match ($item->source_type) {
            BillingDocument::class => $item->event_type === EconomicEventType::CreditNoteIssued->value
                ? $this->creditNoteDefinition(BillingDocument::query()->where('organization_id', $item->organization_id)->with(['order.items.product', 'relatedDocument'])->findOrFail($item->source_id))
                : $this->invoiceDefinition(BillingDocument::query()->where('organization_id', $item->organization_id)->with('order.items.product')->findOrFail($item->source_id)),
            Order::class => $this->paymentDefinition(Order::query()->where('organization_id', $item->organization_id)->with(['items.product', 'billingDocuments'])->findOrFail($item->source_id)),
            InventoryDocument::class => $this->inventoryDefinition(
                Order::query()->where('organization_id', $item->organization_id)
                    ->where($item->event_type === EconomicEventType::InventoryReturned->value ? 'return_document_id' : 'dispatch_document_id', $item->source_id)
                    ->with([$item->event_type === EconomicEventType::InventoryReturned->value ? 'returnDocument.items.movement' : 'dispatchDocument.items.movement', 'items.product'])->firstOrFail(),
                InventoryDocument::query()->where('organization_id', $item->organization_id)->with('items.movement')->findOrFail($item->source_id),
                $item->event_type === EconomicEventType::InventoryReturned->value,
            ),
            default => throw new DomainException('Tipo de fuente histórica no soportado.'),
        };
        if ($definition['issues'] !== []) {
            throw new DomainException('La fuente histórica dejó de ser válida: '.$definition['issues'][0]['message']);
        }

        return $definition;
    }

    /** @param array<string,mixed> $definition */
    private function manualDuplicateExists(AccountingActivationRun $run, array $definition): bool
    {
        $orderId = (int) ($definition['payload']['order_id'] ?? 0);
        if ($orderId > 0 && AccountingEntry::query()->where('organization_id', $run->organization_id)
            ->where('origin', 'manual')->whereHas('lines', fn ($query) => $query->where('order_id', $orderId))->exists()) {
            return true;
        }
        if ($definition['source_type'] !== BillingDocument::class) {
            return false;
        }
        $payload = $definition['payload'];

        return AccountingEntry::query()->where('organization_id', $run->organization_id)
            ->where('origin', 'manual')->where('voucher_series', $payload['series'] ?? null)
            ->where('voucher_number', isset($payload['number']) ? (string) $payload['number'] : null)->exists();
    }

    /** @return array<string,mixed> */
    private function configurationSnapshot(int $organizationId, CarbonImmutable $cutoff, CarbonImmutable $through): array
    {
        $settings = AccountingSetting::query()->where('organization_id', $organizationId)->first();

        return [
            'settings' => $settings?->toArray(),
            'accounts' => AccountingAccount::query()->where('organization_id', $organizationId)->orderBy('code')->get()->map->only([
                'id', 'code', 'name', 'type', 'is_active', 'is_default_sales', 'is_default_receivable',
                'is_default_tax', 'updated_at',
            ])->all(),
            'periods' => AccountingPeriod::query()->where('organization_id', $organizationId)
                ->where(function ($query) use ($cutoff, $through): void {
                    $query->where('year', '>', $cutoff->year)->orWhere(fn ($q) => $q->where('year', $cutoff->year)->where('month', '>=', $cutoff->month));
                })->where(function ($query) use ($through): void {
                    $query->where('year', '<', $through->year)->orWhere(fn ($q) => $q->where('year', $through->year)->where('month', '<=', $through->month));
                })->orderBy('year')->orderBy('month')->get()->map->only(['id', 'year', 'month', 'status', 'updated_at'])->all(),
            'timezone' => 'UTC',
        ];
    }

    /** @return array<int,array{code:string,message:string}> */
    private function configurationIssues(int $organizationId): array
    {
        $settings = AccountingSetting::query()->where('organization_id', $organizationId)->first();
        if (! $settings) {
            return [$this->issue('missing_accounting_settings', 'La organización no tiene configuración contable.')];
        }

        $issues = [];
        foreach (['revenue', 'receivable', 'tax', 'cash', 'inventory', 'cogs'] as $role) {
            $code = trim((string) $settings->{'default_account_'.$role});
            if ($code === '') {
                $issues[] = $this->issue('missing_default_account_'.$role, "Falta la cuenta explícita para {$role}.");
                continue;
            }
            if (! AccountingAccount::query()->where('organization_id', $organizationId)->where('code', $code)->where('is_active', true)->exists()) {
                $issues[] = $this->issue('inactive_default_account_'.$role, "La cuenta {$code} para {$role} no existe o está inactiva.");
            }
        }

        return $issues;
    }

    private function assertSnapshotCurrent(AccountingActivationRun $run): void
    {
        $current = $this->configurationSnapshot((int) $run->organization_id, $run->cutoff_at, $run->captured_through_at);
        if (! hash_equals($this->hash($run->configuration_snapshot), $this->hash($current))) {
            throw new DomainException('La configuración o los periodos cambiaron; debe ejecutar una nueva simulación.');
        }
        $items = $run->items()->orderBy('dependency_order')->orderBy('occurred_at')->orderBy('id')->get();
        if (! hash_equals($run->simulation_hash, $this->runHash($run, $run->configuration_snapshot, $items))) {
            throw new DomainException('El snapshot de simulación ya no es íntegro.');
        }
        if (! hash_equals($this->storedManifestHash($items), $this->currentManifestHash($run))) {
            throw new DomainException('El manifiesto de fuentes cambió; debe ejecutar una nueva simulación.');
        }
        foreach ($items->where('status', 'eligible') as $item) {
            $definition = $this->definitionForItem($item);
            $deferredPreview = $item->event_type === EconomicEventType::CreditNoteIssued->value
                && data_get($item->configuration_snapshot, 'deferred_validation') === true;
            $preview = $deferredPreview ? ['deferred_validation' => true] : $this->previewDefinition($definition);
            if (! hash_equals($item->simulation_hash, $this->itemSimulationHash($definition, $preview))) {
                throw new DomainException('Una fuente o su configuración contable cambió después de la simulación.');
            }
        }
        foreach ($items->where('status', 'already_present') as $item) {
            $event = AccountingEconomicEvent::query()->where('organization_id', $run->organization_id)->find($item->accounting_economic_event_id);
            if (! $event || $event->status !== EconomicEventStatus::Processed || ! hash_equals($event->payload_hash, $item->payload_hash)
                || (int) $event->processed_entry_id !== (int) $item->accounting_entry_id) {
                throw new DomainException('Un evento ya contabilizado cambió de estado después de la simulación.');
            }
        }
    }

    private function dependencySatisfied(AccountingActivationRun $run, string $dependencyKey): bool
    {
        if (AccountingEconomicEvent::query()->where('organization_id', $run->organization_id)
            ->where('idempotency_key', $dependencyKey)->where('status', EconomicEventStatus::Processed->value)->exists()) {
            return true;
        }

        return $run->items()->where('idempotency_key', $dependencyKey)
            ->whereIn('status', ['eligible', 'already_present', 'processed'])->exists();
    }

    private function storedManifestHash(Collection $items): string
    {
        return $this->hash($items->map(fn (AccountingActivationItem $item) => [
            'type' => $item->event_type, 'source_type' => $item->source_type, 'source_id' => $item->source_id,
            'idempotency_key' => $item->idempotency_key, 'payload_hash' => $item->payload_hash,
            'occurred_at' => $item->occurred_at?->toISOString(),
        ])->sortBy(fn (array $row) => implode('|', [$row['type'], $row['source_type'], $row['source_id']]))->values()->all());
    }

    private function currentManifestHash(AccountingActivationRun $run): string
    {
        return $this->hash($this->definitions((int) $run->organization_id, $run->cutoff_at, $run->captured_through_at)
            ->map(fn (array $definition) => [
                'type' => $definition['type']->value, 'source_type' => $definition['source_type'],
                'source_id' => $definition['source_id'], 'idempotency_key' => $definition['idempotency_key'],
                'payload_hash' => $this->payloadHash($definition['payload']),
                'occurred_at' => $definition['occurred_at']?->toISOString(),
            ])->sortBy(fn (array $row) => implode('|', [$row['type'], $row['source_type'], $row['source_id']]))->values()->all());
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $preview */
    private function itemSimulationHash(array $definition, array $preview): string
    {
        return $this->hash(['idempotency_key' => $definition['idempotency_key'], 'payload' => $definition['payload'],
            'occurred_at' => $definition['occurred_at']?->toISOString(), 'preview' => $preview]);
    }

    private function runHash(AccountingActivationRun $run, array $configuration, Collection $items): string
    {
        return $this->hash(['organization_id' => $run->organization_id, 'cutoff_at' => $run->cutoff_at->toISOString(),
            'captured_through_at' => $run->captured_through_at->toISOString(), 'configuration' => $configuration,
            'items' => $items->map(fn ($item) => [$item->id, $item->status, $item->simulation_hash, $item->issues])->all()]);
    }

    private function payloadHash(array $payload): string
    {
        return $this->hash($payload);
    }

    private function hash(mixed $value): string
    {
        return hash('sha256', json_encode($this->normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /** @return array<string,mixed> */
    private function definition(EconomicEventType $type, string $sourceType, int $sourceId, ?string $sourceCode,
        string $idempotencyKey, array $payload, mixed $occurredAt, ?int $branchId, int $dependencyOrder,
        ?string $dependencyKey, array $issues): array
    {
        return compact('type', 'sourceType', 'sourceId', 'sourceCode', 'idempotencyKey', 'payload',
            'occurredAt', 'branchId', 'dependencyOrder', 'dependencyKey', 'issues') + [
                'organization_id' => (int) ($payload['organization_id'] ?? match ($sourceType) {
                    BillingDocument::class => BillingDocument::query()->whereKey($sourceId)->value('organization_id'),
                    Order::class => Order::query()->whereKey($sourceId)->value('organization_id'),
                    InventoryDocument::class => InventoryDocument::query()->whereKey($sourceId)->value('organization_id'),
                    default => 0,
                }),
                'source_type' => $sourceType, 'source_id' => $sourceId, 'source_code' => $sourceCode,
                'idempotency_key' => $idempotencyKey, 'occurred_at' => $occurredAt,
                'branch_id' => $branchId, 'dependency_order' => $dependencyOrder,
                'dependency_key' => $dependencyKey,
            ];
    }

    /** @return array{code:string,message:string} */
    private function issue(string $code, string $message): array
    {
        return compact('code', 'message');
    }
}
