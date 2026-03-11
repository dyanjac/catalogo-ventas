<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\EnsureSuperAdmin;
use Modules\ElectronicDocuments\Http\Controllers\DocumentTemplateController;
use Modules\ElectronicDocuments\Http\Controllers\InvoicePdfController;

Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('admin/electronic-documents/templates', [DocumentTemplateController::class, 'index'])
        ->name('admin.electronic-documents.templates.index');
    Route::get('admin/electronic-documents/templates/create', [DocumentTemplateController::class, 'create'])
        ->name('admin.electronic-documents.templates.create');
    Route::post('admin/electronic-documents/templates', [DocumentTemplateController::class, 'store'])
        ->name('admin.electronic-documents.templates.store');
    Route::get('admin/electronic-documents/templates/{template}/edit', [DocumentTemplateController::class, 'edit'])
        ->name('admin.electronic-documents.templates.edit');
    Route::put('admin/electronic-documents/templates/{template}', [DocumentTemplateController::class, 'update'])
        ->name('admin.electronic-documents.templates.update');
    Route::delete('admin/electronic-documents/templates/{template}', [DocumentTemplateController::class, 'destroy'])
        ->name('admin.electronic-documents.templates.destroy');
    Route::post('admin/electronic-documents/templates/{template}/toggle', [DocumentTemplateController::class, 'toggle'])
        ->name('admin.electronic-documents.templates.toggle');
    Route::post('admin/electronic-documents/templates/preview', [DocumentTemplateController::class, 'preview'])
        ->name('admin.electronic-documents.templates.preview');

    Route::get('admin/electronic-documents/pdf/{serieNumero}', [InvoicePdfController::class, 'generate'])
        ->name('admin.electronic-documents.pdf.generate');
});
