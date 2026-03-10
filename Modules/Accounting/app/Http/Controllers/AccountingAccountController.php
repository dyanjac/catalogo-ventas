<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\AccountingAccount;
use Modules\Accounting\Services\AccountingAuditService;

class AccountingAccountController extends Controller
{
    public function __construct(private readonly AccountingAuditService $audit)
    {
    }

    public function index(): View
    {
        return view('accounting::accounts.index', [
            'accounts' => AccountingAccount::query()->with('parent')->orderBy('code')->paginate(30),
            'parents' => AccountingAccount::query()->orderBy('code')->get(['id', 'code', 'name']),
            'types' => ['activo', 'pasivo', 'patrimonio', 'ingreso', 'gasto'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:accounting_accounts,code'],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:activo,pasivo,patrimonio,ingreso,gasto'],
            'parent_id' => ['nullable', 'exists:accounting_accounts,id'],
            'level' => ['nullable', 'integer', 'min:1', 'max:9'],
            'is_active' => ['nullable', 'boolean'],
            'is_default_sales' => ['nullable', 'boolean'],
            'is_default_purchase' => ['nullable', 'boolean'],
            'is_default_tax' => ['nullable', 'boolean'],
        ]);

        $account = AccountingAccount::query()->create([
            ...$data,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_default_sales' => (bool) ($data['is_default_sales'] ?? false),
            'is_default_purchase' => (bool) ($data['is_default_purchase'] ?? false),
            'is_default_tax' => (bool) ($data['is_default_tax'] ?? false),
            'level' => (int) ($data['level'] ?? 1),
        ]);

        $this->audit->log('account', (int) $account->id, 'create', $account->toArray());

        return back()->with('success', 'Cuenta contable creada correctamente.');
    }

    public function update(Request $request, AccountingAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:accounting_accounts,code,' . $account->id],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:activo,pasivo,patrimonio,ingreso,gasto'],
            'parent_id' => ['nullable', 'exists:accounting_accounts,id'],
            'level' => ['nullable', 'integer', 'min:1', 'max:9'],
            'is_active' => ['nullable', 'boolean'],
            'is_default_sales' => ['nullable', 'boolean'],
            'is_default_purchase' => ['nullable', 'boolean'],
            'is_default_tax' => ['nullable', 'boolean'],
        ]);

        $before = $account->toArray();
        $account->update([
            ...$data,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_default_sales' => (bool) ($data['is_default_sales'] ?? false),
            'is_default_purchase' => (bool) ($data['is_default_purchase'] ?? false),
            'is_default_tax' => (bool) ($data['is_default_tax'] ?? false),
            'level' => (int) ($data['level'] ?? 1),
        ]);

        $this->audit->log('account', (int) $account->id, 'update', ['before' => $before, 'after' => $account->fresh()->toArray()]);

        return back()->with('success', 'Cuenta contable actualizada correctamente.');
    }
}
