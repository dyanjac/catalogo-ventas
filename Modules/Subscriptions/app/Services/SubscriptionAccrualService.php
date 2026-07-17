<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounting\Enums\EconomicEventStatus;
use Modules\Accounting\Enums\EconomicEventType;
use Modules\Accounting\Models\AccountingEconomicEvent;
use Modules\Accounting\Services\EconomicEventService;
use Modules\Billing\Models\BillingDocument;
use Modules\Subscriptions\Enums\AccrualStatus;
use Modules\Subscriptions\Models\SubscriptionAccrualSchedule;
use Throwable;

final class SubscriptionAccrualService
{
    public function __construct(private readonly EconomicEventService $economicEvents) {}

    /** @return array<int,int> */
    public function claimDue(string $through, int $limit = 500, ?int $organizationId = null): array
    {
        return DB::transaction(function () use ($through, $limit, $organizationId): array {
            $query = SubscriptionAccrualSchedule::query()
                ->whereIn('status', [AccrualStatus::Pending->value, AccrualStatus::Error->value, AccrualStatus::Claimed->value])
                ->whereDate('due_on', '<=', $through)
                ->whereHas('period', fn ($period) => $period->whereNotNull('billing_document_id'))
                ->where(function ($q): void {
                    $q->whereNull('claimed_at')->orWhere('claimed_at', '<=', now('UTC')->subMinutes((int) config('subscriptions.claim_lease_minutes', 15)));
                })
                ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
                ->orderBy('due_on')->orderBy('id')->limit(max(1, min($limit, 1000)))->lockForUpdate();
            $rows = $query->get();
            foreach ($rows as $row) {
                $row->forceFill(['status' => AccrualStatus::Claimed, 'lease_token' => (string) Str::uuid(), 'claimed_at' => now('UTC'), 'attempts' => $row->attempts + 1])->save();
            }

            return $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
        }, 3);
    }

    public function process(int $organizationId, int $scheduleId): SubscriptionAccrualSchedule
    {
        try {
            return DB::transaction(function () use ($organizationId, $scheduleId): SubscriptionAccrualSchedule {
                $schedule = SubscriptionAccrualSchedule::query()->where('organization_id', $organizationId)->lockForUpdate()->findOrFail($scheduleId);
                if ($schedule->status === AccrualStatus::EventRecorded) {
                    return $schedule;
                }
                if ($schedule->status === AccrualStatus::Cancelled) {
                    return $schedule;
                }
                if ($schedule->status !== AccrualStatus::Claimed) {
                    throw new \DomainException('El devengamiento no estÃ¡ reclamado para procesamiento.');
                }
                $schedule->loadMissing(['subscription', 'period.billingDocument.order.items']);
                $document = $schedule->period?->billingDocument;
                if (! $document || $document->status !== 'issued') {
                    throw new \DomainException('El periodo no tiene un comprobante anticipado emitido.');
                }
                if ((int) round(((float) $document->subtotal) * 100) !== (int) $schedule->period->subtotal_minor
                    || strtoupper((string) $document->currency) !== strtoupper((string) $schedule->currency)) {
                    throw new \DomainException('El comprobante no coincide con el importe o moneda del periodo.');
                }
                if (BillingDocument::query()->where('related_document_id', $document->id)
                    ->where('document_type', 'credit_note')->where('status', 'issued')->exists()) {
                    throw new \DomainException('El comprobante fue afectado por una nota de crÃ©dito; ajuste o cancele el calendario antes de devengar.');
                }
                $invoiceEvent = AccountingEconomicEvent::query()
                    ->where('organization_id', $organizationId)
                    ->where('event_type', EconomicEventType::InvoiceIssued->value)
                    ->where('source_type', BillingDocument::class)
                    ->where('source_id', $document->id)->first();
                if (! $invoiceEvent || $invoiceEvent->status !== EconomicEventStatus::Processed) {
                    throw new \DomainException('El ingreso diferido de la factura debe contabilizarse antes de devengar.');
                }
                $deferredEvent = AccountingEconomicEvent::query()
                    ->where('organization_id', $organizationId)
                    ->where('event_type', EconomicEventType::SubscriptionDeferred->value)
                    ->where('source_type', \Modules\Subscriptions\Models\SubscriptionServicePeriod::class)
                    ->where('source_id', $schedule->service_period_id)->first();
                if (! $deferredEvent || $deferredEvent->status !== EconomicEventStatus::Processed) {
                    throw new \DomainException('La reclasificaciÃ³n a ingreso diferido debe procesarse antes de devengar.');
                }
                $event = $this->economicEvents->record(
                    $organizationId,
                    EconomicEventType::ServiceAccrued,
                    "subscription-accrual:{$schedule->id}:v{$schedule->revision}",
                    SubscriptionAccrualSchedule::class,
                    (int) $schedule->id,
                    $schedule->subscription->code.'-DEV-'.$schedule->id,
                    [
                        'subscription_id' => (int) $schedule->subscription_id,
                        'product_id' => (int) $schedule->subscription->product_id,
                        'currency' => $schedule->currency,
                        'amount_minor' => (int) $schedule->amount_minor,
                        'kind' => $schedule->kind,
                        'reason' => $schedule->reason,
                        'service_starts_on' => $schedule->service_starts_on->toDateString(),
                        'service_ends_on' => $schedule->service_ends_on->toDateString(),
                        'accounts' => (array) data_get($schedule->period->accounting_snapshot, 'accounts', []),
                    ],
                    $schedule->due_on,
                    null,
                    $schedule->subscription->branch_id,
                );
                $schedule->forceFill([
                    'status' => AccrualStatus::EventRecorded,
                    'accounting_economic_event_id' => $event->id,
                    'event_recorded_at' => now('UTC'), 'lease_token' => null, 'claimed_at' => null,
                    'error_code' => null, 'error_message' => null,
                ])->save();

                return $schedule->fresh('economicEvent');
            }, 3);
        } catch (Throwable $e) {
            SubscriptionAccrualSchedule::query()->where('organization_id', $organizationId)->whereKey($scheduleId)
                ->where('status', AccrualStatus::Claimed->value)->update([
                    'status' => AccrualStatus::Error->value, 'lease_token' => null, 'claimed_at' => null,
                    'error_code' => class_basename($e), 'error_message' => mb_substr($e->getMessage(), 0, 2000), 'updated_at' => now(),
                ]);
            throw $e;
        }
    }
}
