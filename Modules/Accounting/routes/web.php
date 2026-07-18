<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AccountingAccountController;
use Modules\Accounting\Http\Controllers\AccountingActivationController;
use Modules\Accounting\Http\Controllers\AccountingEconomicEventController;
use Modules\Accounting\Http\Controllers\AccountingEntryController;
use Modules\Accounting\Http\Controllers\AccountingPeriodController;
use Modules\Accounting\Http\Controllers\AccountingSettingsController;
use Modules\Accounting\Http\Controllers\CostCenterController;

Route::middleware(['auth', 'security.module:accounting'])->group(function () {
    Route::get('admin/accounting/settings', [AccountingSettingsController::class, 'edit'])
        ->name('admin.accounting.settings.edit');
    Route::put('admin/accounting/settings', [AccountingSettingsController::class, 'update'])
        ->middleware('security.permission:accounting.settings.configure')
        ->name('admin.accounting.settings.update');

    Route::get('admin/accounting/accounts', [AccountingAccountController::class, 'index'])->name('admin.accounting.accounts.index');
    Route::post('admin/accounting/accounts', [AccountingAccountController::class, 'store'])->name('admin.accounting.accounts.store');
    Route::post('admin/accounting/accounts/setup-default-sales-chart', [AccountingAccountController::class, 'setupDefaultSalesChart'])->name('admin.accounting.accounts.setup-default-sales-chart');
    Route::delete('admin/accounting/accounts/reset-chart', [AccountingAccountController::class, 'resetChart'])->name('admin.accounting.accounts.reset-chart');
    Route::put('admin/accounting/accounts/{account}', [AccountingAccountController::class, 'update'])->name('admin.accounting.accounts.update');

    Route::get('admin/accounting/periods', [AccountingPeriodController::class, 'index'])->name('admin.accounting.periods.index');
    Route::post('admin/accounting/periods', [AccountingPeriodController::class, 'store'])->name('admin.accounting.periods.store');
    Route::put('admin/accounting/periods/{period}', [AccountingPeriodController::class, 'update'])->name('admin.accounting.periods.update');

    Route::get('admin/accounting/cost-centers', [CostCenterController::class, 'index'])->name('admin.accounting.cost-centers.index');
    Route::post('admin/accounting/cost-centers', [CostCenterController::class, 'store'])->name('admin.accounting.cost-centers.store');
    Route::put('admin/accounting/cost-centers/{costCenter}', [CostCenterController::class, 'update'])->name('admin.accounting.cost-centers.update');

    Route::get('admin/accounting/entries', [AccountingEntryController::class, 'index'])
        ->middleware('security.permission:accounting.entries.view')
        ->name('admin.accounting.entries.index');
    Route::get('admin/accounting/entries/{entry}/edit', [AccountingEntryController::class, 'edit'])
        ->middleware('security.permission:accounting.entries.view')
        ->name('admin.accounting.entries.edit');
    Route::put('admin/accounting/entries/{entry}', [AccountingEntryController::class, 'update'])
        ->middleware('security.permission:accounting.entries.update')
        ->name('admin.accounting.entries.update');
    Route::delete('admin/accounting/entries/{entry}/attachments/{attachment}', [AccountingEntryController::class, 'destroyAttachment'])
        ->middleware('security.permission:accounting.entries.update')
        ->name('admin.accounting.entries.attachments.destroy');

    Route::get('admin/accounting/events', [AccountingEconomicEventController::class, 'index'])
        ->middleware('security.permission:accounting.events.view')
        ->name('admin.accounting.events.index');
    Route::get('admin/accounting/events/{event}', [AccountingEconomicEventController::class, 'show'])
        ->middleware('security.permission:accounting.events.view')
        ->name('admin.accounting.events.show');
    Route::post('admin/accounting/events/{event}/process', [AccountingEconomicEventController::class, 'process'])
        ->middleware('security.permission:accounting.events.process')
        ->name('admin.accounting.events.process');
    Route::post('admin/accounting/events/{event}/reverse', [AccountingEconomicEventController::class, 'reverse'])
        ->middleware('security.permission:accounting.events.reverse')
        ->name('admin.accounting.events.reverse');

    Route::get('admin/accounting/historical-activations', [AccountingActivationController::class, 'index'])
        ->middleware('security.permission:accounting.history.view')
        ->name('admin.accounting.activations.index');
    Route::post('admin/accounting/historical-activations', [AccountingActivationController::class, 'store'])
        ->middleware('security.permission:accounting.history.simulate')
        ->name('admin.accounting.activations.store');
    Route::get('admin/accounting/historical-activations/{activation}', [AccountingActivationController::class, 'show'])
        ->middleware('security.permission:accounting.history.view')
        ->name('admin.accounting.activations.show');
    Route::post('admin/accounting/historical-activations/{activation}/confirm', [AccountingActivationController::class, 'confirm'])
        ->middleware('security.permission:accounting.history.confirm')
        ->name('admin.accounting.activations.confirm');
    Route::post('admin/accounting/historical-activations/{activation}/reprocess', [AccountingActivationController::class, 'reprocess'])
        ->middleware('security.permission:accounting.history.reprocess')
        ->name('admin.accounting.activations.reprocess');
});
