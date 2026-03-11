<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\BillingDocumentController;
use Modules\Billing\Http\Controllers\BillingSettingsController;
use Modules\Core\Http\Middleware\EnsureSuperAdmin;

Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('admin/billing/settings', [BillingSettingsController::class, 'edit'])->name('admin.billing.settings.edit');
    Route::put('admin/billing/settings', [BillingSettingsController::class, 'update'])->name('admin.billing.settings.update');
    Route::post('admin/billing/settings/test-connection', [BillingSettingsController::class, 'testConnection'])->name('admin.billing.settings.test-connection');
    Route::get('admin/billing/operation-types', [BillingSettingsController::class, 'editOperationTypes'])->name('admin.billing.operation-types.edit');
    Route::put('admin/billing/operation-types', [BillingSettingsController::class, 'updateOperationTypes'])->name('admin.billing.operation-types.update');

    Route::get('admin/billing/documents', [BillingDocumentController::class, 'index'])->name('admin.billing.documents.index');
    Route::get('admin/billing/documents/{document}', [BillingDocumentController::class, 'show'])->name('admin.billing.documents.show');
    Route::post('admin/billing/documents/{document}/redeclare', [BillingDocumentController::class, 'redeclare'])->name('admin.billing.documents.redeclare');
    Route::get('admin/billing/documents/{document}/xml', [BillingDocumentController::class, 'downloadXml'])->name('admin.billing.documents.download.xml');
    Route::get('admin/billing/documents/{document}/cdr', [BillingDocumentController::class, 'downloadCdr'])->name('admin.billing.documents.download.cdr');
    Route::get('admin/billing/documents/{document}/pdf', [BillingDocumentController::class, 'downloadPdf'])->name('admin.billing.documents.download.pdf');
});
