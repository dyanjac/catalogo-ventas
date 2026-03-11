<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\EnsureSuperAdmin;
use Modules\Sales\Http\Controllers\SalesPosController;

Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('admin/sales/pos', [SalesPosController::class, 'index'])->name('admin.sales.pos.index');
    Route::post('admin/sales/pos', [SalesPosController::class, 'store'])->name('admin.sales.pos.store');
    Route::post('admin/sales/pos/customer-lookup', [SalesPosController::class, 'lookupCustomerDocument'])->name('admin.sales.pos.customer-lookup');
});
