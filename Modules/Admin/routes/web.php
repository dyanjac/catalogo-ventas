<?php

use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\CategoryController as AdminCategoryController;
use Modules\Admin\Http\Controllers\CustomerController as AdminCustomerController;
use Modules\Admin\Http\Controllers\DashboardController as AdminDashboardController;
use Modules\Admin\Http\Controllers\OrderController as AdminOrderController;
use Modules\Admin\Http\Controllers\ProductController as AdminProductController;
use Modules\Admin\Http\Controllers\ProductImageController as AdminProductImageController;
use Modules\Admin\Http\Controllers\UnitMeasureController as AdminUnitMeasureController;
use Modules\Commerce\Http\Controllers\CommerceSettingController as CommerceSettingAdminController;

Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('admin', AdminDashboardController::class)->name('admin.dashboard');
    Route::get('admin/settings', [CommerceSettingAdminController::class, 'edit'])->name('admin.settings.edit');
    Route::put('admin/settings', [CommerceSettingAdminController::class, 'update'])->name('admin.settings.update');
    Route::resource('admin/products', AdminProductController::class)->names('admin.products');
    Route::post('admin/products/{product}/images', [AdminProductImageController::class, 'store'])->name('admin.products.images.store');
    Route::delete('admin/products/{product}/images/{image}', [AdminProductImageController::class, 'destroy'])->name('admin.products.images.destroy');

    Route::resource('admin/categories', AdminCategoryController::class)
        ->except(['show'])
        ->names('admin.categories');

    Route::resource('admin/unit-measures', AdminUnitMeasureController::class)
        ->except(['show'])
        ->names('admin.unit-measures');

    Route::get('admin/customers', [AdminCustomerController::class, 'index'])->name('admin.customers.index');
    Route::get('admin/customers/{customer}', [AdminCustomerController::class, 'show'])->name('admin.customers.show');
    Route::put('admin/customers/{customer}', [AdminCustomerController::class, 'update'])->name('admin.customers.update');

    Route::get('admin/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
    Route::get('admin/orders/{order}', [AdminOrderController::class, 'show'])->name('admin.orders.show');
    Route::get('admin/orders/{order}/pdf', [AdminOrderController::class, 'downloadPdf'])->name('admin.orders.download.pdf');
    Route::put('admin/orders/{order}', [AdminOrderController::class, 'update'])->name('admin.orders.update');
});
