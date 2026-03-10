<?php

use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\SalesPosController;

Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('admin/sales/pos', [SalesPosController::class, 'index'])->name('admin.sales.pos.index');
    Route::post('admin/sales/pos', [SalesPosController::class, 'store'])->name('admin.sales.pos.store');
});
