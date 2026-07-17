<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Accounting\Models\AccountingSetting;
use Modules\Catalog\Enums\ProductAccountingTreatment;

class AccountingSettingsController extends Controller
{
    public function __construct(private readonly OrganizationContextService $organizationContext) {}

    public function edit(): View
    {
        $settings = AccountingSetting::query()->firstOrCreate(
            ['organization_id' => $this->organizationContext->currentOrganizationId()],
            [
                'fiscal_year' => now()->year,
                'fiscal_year_start_month' => 1,
                'default_currency' => config('accounting.default_currency', 'PEN'),
                'period_closure_enabled' => false,
                'auto_post_entries' => false,
                'product_accounting_treatment' => ProductAccountingTreatment::PendingConfiguration,
            ]
        );

        return view('accounting::settings.edit', [
            'settings' => $settings,
            'accountingTreatments' => array_values(array_filter(
                ProductAccountingTreatment::cases(),
                fn (ProductAccountingTreatment $treatment): bool => $treatment !== ProductAccountingTreatment::Inherit
            )),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureTenantOperational();

        $data = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'fiscal_year_start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'default_currency' => ['required', 'string', 'size:3'],
            'period_closure_enabled' => ['nullable', 'boolean'],
            'auto_post_entries' => ['nullable', 'boolean'],
            'product_accounting_treatment' => [
                'sometimes',
                Rule::enum(ProductAccountingTreatment::class)->except(ProductAccountingTreatment::Inherit),
            ],
            'default_account_revenue' => ['nullable', 'string', 'max:120'],
            'default_account_deferred_revenue' => ['nullable', 'string', 'max:120'],
            'default_account_receivable' => ['nullable', 'string', 'max:120'],
            'default_account_inventory' => ['nullable', 'string', 'max:120'],
            'default_account_cogs' => ['nullable', 'string', 'max:120'],
            'default_account_tax' => ['nullable', 'string', 'max:120'],
            'default_account_cash' => ['nullable', 'string', 'max:120'],
        ]);

        $organizationId = $this->organizationContext->currentOrganizationId();
        $settings = AccountingSetting::query()->where('organization_id', $organizationId)->first();

        foreach (['default_account_revenue', 'default_account_deferred_revenue', 'default_account_receivable', 'default_account_inventory', 'default_account_cogs', 'default_account_tax', 'default_account_cash'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? trim((string) $data[$field]) : null;
            }
        }

        foreach (['default_account_revenue', 'default_account_deferred_revenue', 'default_account_receivable', 'default_account_inventory', 'default_account_cogs', 'default_account_tax', 'default_account_cash'] as $field) {
            if (filled($data[$field] ?? null)) {
                $exists = \Modules\Accounting\Models\AccountingAccount::query()
                    ->where('organization_id', $organizationId)
                    ->where('code', $data[$field])
                    ->where('is_active', true)
                    ->exists();
                if (! $exists) {
                    throw ValidationException::withMessages([$field => 'La cuenta debe existir, estar activa y pertenecer a la organización actual.']);
                }
            }
        }

        AccountingSetting::query()->updateOrCreate(
            ['organization_id' => $organizationId],
            [
                'fiscal_year' => (int) $data['fiscal_year'],
                'fiscal_year_start_month' => (int) $data['fiscal_year_start_month'],
                'default_currency' => strtoupper((string) $data['default_currency']),
                'period_closure_enabled' => (bool) ($data['period_closure_enabled'] ?? false),
                'auto_post_entries' => (bool) ($data['auto_post_entries'] ?? false),
                'product_accounting_treatment' => $data['product_accounting_treatment']
                    ?? $settings?->product_accounting_treatment
                    ?? ProductAccountingTreatment::PendingConfiguration,
                'default_account_revenue' => array_key_exists('default_account_revenue', $data) ? $data['default_account_revenue'] : $settings?->default_account_revenue,
                'default_account_deferred_revenue' => array_key_exists('default_account_deferred_revenue', $data) ? $data['default_account_deferred_revenue'] : $settings?->default_account_deferred_revenue,
                'default_account_receivable' => array_key_exists('default_account_receivable', $data) ? $data['default_account_receivable'] : $settings?->default_account_receivable,
                'default_account_inventory' => array_key_exists('default_account_inventory', $data) ? $data['default_account_inventory'] : $settings?->default_account_inventory,
                'default_account_cogs' => array_key_exists('default_account_cogs', $data) ? $data['default_account_cogs'] : $settings?->default_account_cogs,
                'default_account_tax' => array_key_exists('default_account_tax', $data) ? $data['default_account_tax'] : $settings?->default_account_tax,
                'default_account_cash' => array_key_exists('default_account_cash', $data) ? $data['default_account_cash'] : $settings?->default_account_cash,
            ]
        );

        return redirect()
            ->route('admin.accounting.settings.edit')
            ->with('success', 'Configuración contable actualizada correctamente.');
    }

    private function ensureTenantOperational(): void
    {
        if (! $this->organizationContext->isSuspended()) {
            return;
        }

        throw ValidationException::withMessages([
            'accounting' => 'La organización actual está suspendida y no permite cambios contables.',
        ]);
    }
}
