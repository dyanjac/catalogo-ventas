<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Models\AccountingAccount;
use Modules\Accounting\Models\AccountingEntry;
use Modules\Accounting\Models\AccountingPeriod;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Billing\Models\BillingDocument;
use Modules\Catalog\Enums\ProductAccountingTreatment;
use Modules\Commerce\Services\OrganizationEntitlementService;
use Modules\Orders\Entities\Order;

class SalesAccountingService
{
    public function __construct(
        private readonly OrganizationEntitlementService $entitlements,
        private readonly ProductAccountingConfigurationResolver $productAccounting,
    ) {}

    /**
     * @return array{created:bool,message:string,entry_id:int|null}
     */
    public function postIssuedSale(Order $order, ?BillingDocument $document = null): array
    {
        $organization = $order->organization()->first();
        $organizationId = (int) $order->organization_id;

        if (! $organization || ! $this->entitlements->hasCapability('accounting.general_ledger', $organization)) {
            return ['created' => false, 'message' => 'La organización no tiene contratada la generación de asientos contables.', 'entry_id' => null];
        }

        if ($organization->isSuspended()) {
            return ['created' => false, 'message' => 'La organización actual está suspendida y no permite auto-post contable.', 'entry_id' => null];
        }

        $setting = AccountingSetting::query()->where('organization_id', $organizationId)->first();
        if ($setting && ! $setting->auto_post_entries) {
            return ['created' => false, 'message' => 'Auto-post contable desactivado.', 'entry_id' => null];
        }

        $reference = $this->buildReference($order, $document);
        $existing = AccountingEntry::query()
            ->where('organization_id', $organizationId)
            ->where('reference', $reference)
            ->first();
        if ($existing) {
            return ['created' => false, 'message' => 'El asiento ya existe para esta venta.', 'entry_id' => (int) $existing->id];
        }

        $order->loadMissing('items.product');
        $lines = $this->buildLines($order, $organizationId);
        if ($lines->isEmpty()) {
            return ['created' => false, 'message' => 'No hay líneas contables configuradas para esta venta.', 'entry_id' => null];
        }

        $totalDebit = round((float) $lines->sum('debit'), 2);
        $totalCredit = round((float) $lines->sum('credit'), 2);
        if (abs($totalDebit - $totalCredit) > 0.0001) {
            return ['created' => false, 'message' => 'El asiento no cuadra (debe/haber).', 'entry_id' => null];
        }

        $entryDate = now();
        $period = AccountingPeriod::query()
            ->where('organization_id', $organizationId)
            ->where('year', (int) $entryDate->year)
            ->where('month', (int) $entryDate->month)
            ->first();

        if ($period && $period->status === 'closed') {
            return ['created' => false, 'message' => 'Periodo contable cerrado para fecha de emisión.', 'entry_id' => null];
        }

        $entry = AccountingEntry::query()->create([
            'organization_id' => $organizationId,
            'entry_date' => $entryDate->toDateString(),
            'period_year' => (int) $entryDate->year,
            'period_month' => (int) $entryDate->month,
            'voucher_type' => $document?->document_type,
            'voucher_series' => $document?->series,
            'voucher_number' => $document?->number,
            'reference' => $reference,
            'description' => 'Asiento automático por venta '.$order->series.'-'.str_pad((string) $order->order_number, 8, '0', STR_PAD_LEFT),
            'status' => 'posted',
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'posted_at' => now(),
            'created_by' => auth()->id(),
        ]);

        $entry->lines()->createMany($lines->map(function (array $line) use ($order, $organizationId) {
            return [
                'organization_id' => $organizationId,
                'account_code' => $line['account_code'],
                'account_name' => $line['account_name'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'line_description' => $line['line_description'],
                'order_id' => $order->id,
                'product_id' => $line['product_id'] ?? null,
            ];
        })->all());

        return ['created' => true, 'message' => 'Asiento contable generado.', 'entry_id' => (int) $entry->id];
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    private function buildLines(Order $order, int $organizationId): Collection
    {
        $revenueLines = [];
        $taxLines = [];
        $debitTotal = 0.0;
        $receivableCode = null;

        foreach ($order->items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            if ((int) $product->organization_id !== $organizationId) {
                return collect();
            }

            $configuration = $this->productAccounting->resolve($product);
            if (in_array($configuration->treatment, [
                ProductAccountingTreatment::Manual,
                ProductAccountingTreatment::PendingConfiguration,
            ], true)) {
                return collect();
            }

            if (! $configuration->isAutomatic()) {
                continue;
            }

            $receivableCode ??= $configuration->account('receivable');

            $lineTax = round((float) $item->tax_amount, 2);
            $lineTotal = round((float) $item->line_total, 2);
            $lineRevenue = round($lineTotal - $lineTax, 2);

            if ($lineRevenue > 0) {
                $account = $this->resolveRevenueAccount($configuration->account('revenue'), $organizationId);
                if (! $account) {
                    return collect();
                }

                $key = $account->code;
                $revenueLines[$key] = $revenueLines[$key] ?? [
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'debit' => 0.0,
                    'credit' => 0.0,
                    'line_description' => 'Venta de productos',
                    'product_id' => null,
                ];
                $revenueLines[$key]['credit'] = round($revenueLines[$key]['credit'] + $lineRevenue, 2);
                $debitTotal += $lineRevenue;
            }

            if ($lineTax > 0) {
                $account = $this->resolveTaxAccount($configuration->account('tax'), $organizationId);
                if (! $account) {
                    return collect();
                }

                $key = $account->code;
                $taxLines[$key] = $taxLines[$key] ?? [
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'debit' => 0.0,
                    'credit' => 0.0,
                    'line_description' => 'IGV por pagar',
                    'product_id' => null,
                ];
                $taxLines[$key]['credit'] = round($taxLines[$key]['credit'] + $lineTax, 2);
                $debitTotal += $lineTax;
            }
        }

        if ($debitTotal <= 0) {
            return collect();
        }

        $receivable = $this->resolveReceivableAccount($receivableCode, $organizationId);
        if (! $receivable) {
            return collect();
        }

        $debitLine = [
            'account_code' => $receivable->code,
            'account_name' => $receivable->name,
            'debit' => round($debitTotal, 2),
            'credit' => 0.0,
            'line_description' => 'Cuenta por cobrar venta',
            'product_id' => null,
        ];

        return collect([$debitLine, ...array_values($revenueLines), ...array_values($taxLines)]);
    }

    private function resolveRevenueAccount(?string $code, int $organizationId): ?AccountingAccount
    {
        if (filled($code)) {
            return $this->findAccountByCode($code, $organizationId);
        }

        return AccountingAccount::query()->where('organization_id', $organizationId)->where('is_active', true)->where('is_default_sales', true)->first()
            ?? AccountingAccount::query()->where('organization_id', $organizationId)->where('is_active', true)->where('type', 'ingreso')->orderBy('code')->first();
    }

    private function resolveTaxAccount(?string $code, int $organizationId): ?AccountingAccount
    {
        if (filled($code)) {
            return $this->findAccountByCode($code, $organizationId);
        }

        return AccountingAccount::query()->where('organization_id', $organizationId)->where('is_active', true)->where('is_default_tax', true)->first()
            ?? AccountingAccount::query()->where('organization_id', $organizationId)->where('is_active', true)->where('type', 'pasivo')->orderBy('code')->first();
    }

    private function resolveReceivableAccount(?string $code, int $organizationId): ?AccountingAccount
    {
        if (filled($code)) {
            return $this->findAccountByCode($code, $organizationId);
        }

        $defaultReceivable = null;
        if (Schema::hasColumn('accounting_accounts', 'is_default_receivable')) {
            $defaultReceivable = AccountingAccount::query()
                ->where('organization_id', $organizationId)
                ->where('is_active', true)
                ->where('is_default_receivable', true)
                ->first();
        }

        return $defaultReceivable
            ?? AccountingAccount::query()->where('organization_id', $organizationId)->where('is_active', true)->where('type', 'activo')->orderBy('code')->first();
    }

    private function findAccountByCode(?string $code, int $organizationId): ?AccountingAccount
    {
        $value = trim((string) $code);
        if ($value === '') {
            return null;
        }

        return AccountingAccount::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->where('code', $value)
            ->first();
    }

    private function buildReference(Order $order, ?BillingDocument $document): string
    {
        if ($document) {
            return 'VENTA-'.strtoupper((string) $document->document_type).'-'.$document->series.'-'.$document->number;
        }

        return 'VENTA-ORDER-'.$order->id;
    }
}
