<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Enums\EconomicEventType;
use Modules\Accounting\Services\EconomicEventService;
use Modules\Accounting\Services\ProductAccountingConfigurationResolver;
use Modules\Billing\Models\BillingDocument;
use Modules\Subscriptions\Models\SubscriptionServicePeriod;

final class SubscriptionBillingLinkService
{
    public function __construct(
        private readonly EconomicEventService $economicEvents,
        private readonly ProductAccountingConfigurationResolver $productAccounting,
    ) {}

    public function attach(SubscriptionServicePeriod $period, BillingDocument $document): SubscriptionServicePeriod
    {
        return DB::transaction(function () use ($period, $document): SubscriptionServicePeriod {
            $locked = SubscriptionServicePeriod::query()->where('organization_id', $period->organization_id)->lockForUpdate()->findOrFail($period->id);
            $locked->loadMissing('subscription');
            $document = BillingDocument::query()->where('organization_id', $locked->organization_id)->with('order.items')->findOrFail($document->id);
            if ($locked->billing_document_id && (int) $locked->billing_document_id !== (int) $document->id) {
                throw new DomainException('El periodo ya tiene otro comprobante vinculado.');
            }
            if ($document->status !== 'issued' || ! $document->order) {
                throw new DomainException('Solo se puede vincular un comprobante emitido con pedido de origen.');
            }
            if ((int) $document->order->user_id !== (int) $locked->subscription->customer_id) {
                throw new DomainException('El cliente del comprobante no coincide con la suscripciÃ³n.');
            }
            if ($locked->subscription->branch_id && (int) $document->branch_id !== (int) $locked->subscription->branch_id) {
                throw new DomainException('La sucursal del comprobante no coincide con la suscripciÃ³n.');
            }
            if ($document->order->items->count() !== 1) {
                throw new DomainException('El comprobante anticipado debe ser exclusivo para esta suscripciÃ³n.');
            }
            $item = $document->order->items->first(fn ($item) => (int) $item->product_id === (int) $locked->subscription->product_id);
            if (! $item) {
                throw new DomainException('El comprobante no contiene el producto de la suscripciÃ³n.');
            }
            if ((int) round((((float) $item->line_total) - ((float) $item->tax_amount)) * 100) !== (int) $locked->subtotal_minor) {
                throw new DomainException('La lÃ­nea del producto no coincide con el subtotal del periodo.');
            }
            if ((int) round(((float) $document->subtotal) * 100) !== (int) $locked->subtotal_minor
                || (int) round(((float) $document->tax) * 100) !== (int) $locked->tax_minor
                || (int) round(((float) $document->total) * 100) !== (int) $locked->total_minor
                || strtoupper((string) $document->currency) !== strtoupper((string) $locked->subscription->currency)) {
                throw new DomainException('Los importes o la moneda del comprobante no coinciden con el periodo.');
            }
            $configuration = $this->productAccounting->resolve($locked->subscription->product()->firstOrFail());
            if (! $configuration->isAutomatic() || ! $configuration->account('revenue') || ! $configuration->account('deferred_revenue')) {
                throw new DomainException('El producto requiere cuentas explÃ­citas de ingreso e ingreso diferido.');
            }
            $locked->forceFill([
                'billing_document_id' => $document->id,
                'accounting_snapshot' => [
                    'treatment' => $configuration->treatment->value,
                    'accounts' => $configuration->accounts,
                    'sources' => $configuration->accountSources,
                ],
                'status' => 'billed',
            ])->save();
            if (! $locked->subscription->source_order_item_id) {
                $locked->subscription->forceFill(['source_order_item_id' => $item->id])->save();
            }
            $invoiceEvent = $this->economicEvents->recordInvoice($document->order, $document);
            $this->economicEvents->record(
                (int) $locked->organization_id,
                EconomicEventType::SubscriptionDeferred,
                "subscription-period:{$locked->id}:deferred",
                SubscriptionServicePeriod::class,
                (int) $locked->id,
                $locked->subscription->code.'-DIF-'.$locked->sequence,
                [
                    'subscription_id' => (int) $locked->subscription_id,
                    'product_id' => (int) $locked->subscription->product_id,
                    'billing_document_id' => (int) $document->id,
                    'invoice_event_id' => (int) $invoiceEvent->id,
                    'amount_minor' => (int) $locked->subtotal_minor,
                    'currency' => (string) $locked->subscription->currency,
                    'accounts' => $configuration->accounts,
                ],
                $document->issued_at ?? $document->issue_date,
                null,
                $locked->subscription->branch_id,
            );

            return $locked->fresh('billingDocument');
        }, 3);
    }
}
