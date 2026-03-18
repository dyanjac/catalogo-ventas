<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\SalesPosController;

Route::middleware(['auth', 'security.module:pos'])->group(function () {
    Route::get('admin/sales/pos', [SalesPosController::class, 'index'])
        ->middleware('security.permission:pos.sales.view')
        ->name('admin.sales.pos.index');
    Route::post('admin/sales/pos', [SalesPosController::class, 'store'])
        ->middleware('security.permission:pos.sales.create')
        ->name('admin.sales.pos.store');
    Route::post('admin/sales/pos/customer-lookup', [SalesPosController::class, 'lookupCustomerDocument'])
        ->middleware('security.permission:pos.sales.view')
        ->name('admin.sales.pos.customer-lookup');
});
