<?php

use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\BillingDocumentController;
use Modules\Billing\Http\Controllers\BillingSettingsController;

Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('admin/billing/settings', [BillingSettingsController::class, 'edit'])->name('admin.billing.settings.edit');
    Route::put('admin/billing/settings', [BillingSettingsController::class, 'update'])->name('admin.billing.settings.update');
    Route::post('admin/billing/settings/test-connection', [BillingSettingsController::class, 'testConnection'])->name('admin.billing.settings.test-connection');

    Route::get('admin/billing/documents', [BillingDocumentController::class, 'index'])->name('admin.billing.documents.index');
});
