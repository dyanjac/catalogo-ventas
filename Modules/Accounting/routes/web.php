<?php

use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AccountingAccountController;
use Modules\Accounting\Http\Controllers\AccountingEntryController;
use Modules\Accounting\Http\Controllers\AccountingPeriodController;
use Modules\Accounting\Http\Controllers\AccountingSettingsController;
use Modules\Accounting\Http\Controllers\CostCenterController;

Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('admin/accounting/settings', [AccountingSettingsController::class, 'edit'])->name('admin.accounting.settings.edit');
    Route::put('admin/accounting/settings', [AccountingSettingsController::class, 'update'])->name('admin.accounting.settings.update');

    Route::get('admin/accounting/accounts', [AccountingAccountController::class, 'index'])->name('admin.accounting.accounts.index');
    Route::post('admin/accounting/accounts', [AccountingAccountController::class, 'store'])->name('admin.accounting.accounts.store');
    Route::put('admin/accounting/accounts/{account}', [AccountingAccountController::class, 'update'])->name('admin.accounting.accounts.update');

    Route::get('admin/accounting/periods', [AccountingPeriodController::class, 'index'])->name('admin.accounting.periods.index');
    Route::post('admin/accounting/periods', [AccountingPeriodController::class, 'store'])->name('admin.accounting.periods.store');
    Route::put('admin/accounting/periods/{period}', [AccountingPeriodController::class, 'update'])->name('admin.accounting.periods.update');

    Route::get('admin/accounting/cost-centers', [CostCenterController::class, 'index'])->name('admin.accounting.cost-centers.index');
    Route::post('admin/accounting/cost-centers', [CostCenterController::class, 'store'])->name('admin.accounting.cost-centers.store');
    Route::put('admin/accounting/cost-centers/{costCenter}', [CostCenterController::class, 'update'])->name('admin.accounting.cost-centers.update');

    Route::get('admin/accounting/entries', [AccountingEntryController::class, 'index'])->name('admin.accounting.entries.index');
    Route::get('admin/accounting/entries/{entry}/edit', [AccountingEntryController::class, 'edit'])->name('admin.accounting.entries.edit');
    Route::put('admin/accounting/entries/{entry}', [AccountingEntryController::class, 'update'])->name('admin.accounting.entries.update');
    Route::delete('admin/accounting/entries/{entry}/attachments/{attachment}', [AccountingEntryController::class, 'destroyAttachment'])->name('admin.accounting.entries.attachments.destroy');
});
