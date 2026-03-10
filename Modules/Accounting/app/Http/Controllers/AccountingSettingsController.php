<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Accounting\Models\AccountingSetting;

class AccountingSettingsController extends Controller
{
    public function edit(): View
    {
        $settings = AccountingSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'fiscal_year' => now()->year,
                'fiscal_year_start_month' => 1,
                'default_currency' => config('accounting.default_currency', 'PEN'),
                'period_closure_enabled' => false,
                'auto_post_entries' => false,
            ]
        );

        return view('accounting::settings.edit', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'fiscal_year_start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'default_currency' => ['required', 'string', 'size:3'],
            'period_closure_enabled' => ['nullable', 'boolean'],
            'auto_post_entries' => ['nullable', 'boolean'],
        ]);

        AccountingSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'fiscal_year' => (int) $data['fiscal_year'],
                'fiscal_year_start_month' => (int) $data['fiscal_year_start_month'],
                'default_currency' => strtoupper((string) $data['default_currency']),
                'period_closure_enabled' => (bool) ($data['period_closure_enabled'] ?? false),
                'auto_post_entries' => (bool) ($data['auto_post_entries'] ?? false),
            ]
        );

        return redirect()
            ->route('admin.accounting.settings.edit')
            ->with('success', 'Configuración contable actualizada correctamente.');
    }
}
