<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\OrganizationContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Accounting\Models\AccountingAccount;
use Modules\Accounting\Services\AccountingAuditService;

class AccountingAccountController extends Controller
{
    public function __construct(
        private readonly AccountingAuditService $audit,
        private readonly OrganizationContextService $organizationContext
    ) {
    }

    public function index(): View
    {
        return view('accounting::accounts.index', [
            'accounts' => AccountingAccount::query()->forCurrentOrganization()->with('parent')->orderBy('code')->paginate(30),
            'parents' => AccountingAccount::query()->forCurrentOrganization()->orderBy('code')->get(['id', 'code', 'name']),
            'types' => ['activo', 'pasivo', 'patrimonio', 'ingreso', 'gasto'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureTenantOperational();
        $organizationId = $this->organizationContext->currentOrganizationId();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('accounting_accounts', 'code')->where('organization_id', $organizationId)],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:activo,pasivo,patrimonio,ingreso,gasto'],
            'parent_id' => ['nullable', Rule::exists('accounting_accounts', 'id')->where('organization_id', $organizationId)],
            'level' => ['nullable', 'integer', 'min:1', 'max:9'],
            'is_active' => ['nullable', 'boolean'],
            'is_default_sales' => ['nullable', 'boolean'],
            'is_default_purchase' => ['nullable', 'boolean'],
            'is_default_tax' => ['nullable', 'boolean'],
            'is_default_receivable' => ['nullable', 'boolean'],
        ]);

        $account = AccountingAccount::query()->create([
            ...$data,
            'organization_id' => $organizationId,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_default_sales' => (bool) ($data['is_default_sales'] ?? false),
            'is_default_purchase' => (bool) ($data['is_default_purchase'] ?? false),
            'is_default_tax' => (bool) ($data['is_default_tax'] ?? false),
            'is_default_receivable' => (bool) ($data['is_default_receivable'] ?? false),
            'level' => (int) ($data['level'] ?? 1),
        ]);

        $this->audit->log('account', (int) $account->id, 'create', $account->toArray());

        return back()->with('success', 'Cuenta contable creada correctamente.');
    }

    public function update(Request $request, AccountingAccount $account): RedirectResponse
    {
        $this->ensureTenantOperational();
        $organizationId = $this->organizationContext->currentOrganizationId();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('accounting_accounts', 'code')->where('organization_id', $organizationId)->ignore($account->id)],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:activo,pasivo,patrimonio,ingreso,gasto'],
            'parent_id' => ['nullable', Rule::exists('accounting_accounts', 'id')->where('organization_id', $organizationId)],
            'level' => ['nullable', 'integer', 'min:1', 'max:9'],
            'is_active' => ['nullable', 'boolean'],
            'is_default_sales' => ['nullable', 'boolean'],
            'is_default_purchase' => ['nullable', 'boolean'],
            'is_default_tax' => ['nullable', 'boolean'],
            'is_default_receivable' => ['nullable', 'boolean'],
        ]);

        $before = $account->toArray();
        $account->update([
            ...$data,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_default_sales' => (bool) ($data['is_default_sales'] ?? false),
            'is_default_purchase' => (bool) ($data['is_default_purchase'] ?? false),
            'is_default_tax' => (bool) ($data['is_default_tax'] ?? false),
            'is_default_receivable' => (bool) ($data['is_default_receivable'] ?? false),
            'level' => (int) ($data['level'] ?? 1),
        ]);

        $this->audit->log('account', (int) $account->id, 'update', ['before' => $before, 'after' => $account->fresh()->toArray()]);

        return back()->with('success', 'Cuenta contable actualizada correctamente.');
    }

    public function setupDefaultSalesChart(): RedirectResponse
    {
        $this->ensureTenantOperational();

        $seed = [
            [
                'code' => '121201',
                'name' => 'Cuentas por cobrar comerciales',
                'type' => 'activo',
                'is_default_receivable' => true,
                'is_default_sales' => false,
                'is_default_tax' => false,
            ],
            [
                'code' => '701101',
                'name' => 'Ventas de mercaderías',
                'type' => 'ingreso',
                'is_default_receivable' => false,
                'is_default_sales' => true,
                'is_default_tax' => false,
            ],
            [
                'code' => '401111',
                'name' => 'IGV por pagar',
                'type' => 'pasivo',
                'is_default_receivable' => false,
                'is_default_sales' => false,
                'is_default_tax' => true,
            ],
        ];

        $organizationId = $this->organizationContext->currentOrganizationId();

        DB::transaction(function () use ($seed, $organizationId): void {
            AccountingAccount::query()->forCurrentOrganization()->update([
                'is_default_sales' => false,
                'is_default_tax' => false,
                'is_default_receivable' => false,
            ]);

            foreach ($seed as $accountData) {
                AccountingAccount::query()->updateOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'code' => $accountData['code'],
                    ],
                    [
                        'name' => $accountData['name'],
                        'type' => $accountData['type'],
                        'parent_id' => null,
                        'level' => 1,
                        'is_active' => true,
                        'is_default_sales' => $accountData['is_default_sales'],
                        'is_default_purchase' => false,
                        'is_default_tax' => $accountData['is_default_tax'],
                        'is_default_receivable' => $accountData['is_default_receivable'],
                    ]
                );
            }
        });

        $this->audit->log('accounting_setup', 0, 'setup_default_sales_chart', [
            'accounts' => array_column($seed, 'code'),
        ]);

        return back()->with('success', 'Plan de cuentas mínimo de ventas configurado correctamente.');
    }

    public function resetChart(): RedirectResponse
    {
        $this->ensureTenantOperational();

        DB::transaction(function (): void {
            $deleted = AccountingAccount::query()->forCurrentOrganization()->count();

            AccountingAccount::query()->forCurrentOrganization()->delete();

            Product::query()->forCurrentOrganization()->update([
                'account' => null,
                'account_revenue' => null,
                'account_receivable' => null,
                'account_inventory' => null,
                'account_cogs' => null,
                'account_tax' => null,
            ]);

            $this->audit->log('accounting_setup', 0, 'reset_chart', [
                'deleted_accounts' => $deleted,
            ]);
        });

        return back()->with('success', 'Plan de cuentas eliminado. Ya puedes crear uno nuevo.');
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
