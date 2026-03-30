<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\CategoryController as AdminCategoryController;
use Modules\Admin\Http\Controllers\CustomerController as AdminCustomerController;
use Modules\Admin\Http\Controllers\DashboardController as AdminDashboardController;
use Modules\Admin\Http\Controllers\InventoryController as AdminInventoryController;
use Modules\Admin\Http\Controllers\OrderController as AdminOrderController;
use Modules\Admin\Http\Controllers\OrganizationController as AdminOrganizationController;
use Modules\Admin\Http\Controllers\ProductController as AdminProductController;
use Modules\Admin\Http\Controllers\ProductImageController as AdminProductImageController;
use Modules\Admin\Http\Controllers\UnitMeasureController as AdminUnitMeasureController;
use Modules\Commerce\Http\Controllers\CommerceSettingController as CommerceSettingAdminController;

Route::middleware(['auth'])->group(function () {
    Route::get('admin', AdminDashboardController::class)
        ->middleware(['security.module:dashboard', 'security.permission:dashboard.overview.view'])
        ->name('admin.dashboard');

    Route::get('admin/organizations', [AdminOrganizationController::class, 'index'])
        ->middleware(['security.module:security', 'security.permission:security.auth.configure'])
        ->name('admin.organizations.index');
    Route::get('admin/organizations/create', [AdminOrganizationController::class, 'create'])
        ->middleware(['security.module:security', 'security.permission:security.auth.configure'])
        ->name('admin.organizations.create');
    Route::post('admin/organizations', [AdminOrganizationController::class, 'store'])
        ->middleware(['security.module:security', 'security.permission:security.auth.configure'])
        ->name('admin.organizations.store');
    Route::get('admin/organizations/{organization}', [AdminOrganizationController::class, 'show'])
        ->middleware(['security.module:security', 'security.permission:security.auth.configure'])
        ->name('admin.organizations.show');
    Route::put('admin/organizations/{organization}', [AdminOrganizationController::class, 'update'])
        ->middleware(['security.module:security', 'security.permission:security.auth.configure'])
        ->name('admin.organizations.update');
    Route::put('admin/organizations/{organization}/primary-branch', [AdminOrganizationController::class, 'updatePrimaryBranch'])
        ->middleware(['security.module:security', 'security.permission:security.auth.configure'])
        ->name('admin.organizations.primary-branch.update');
    Route::post('admin/organizations/{organization}/primary-branch/recover', [AdminOrganizationController::class, 'recoverPrimaryBranch'])
        ->middleware(['security.module:security', 'security.permission:security.auth.configure'])
        ->name('admin.organizations.primary-branch.recover');
    Route::put('admin/organizations/{organization}/initial-admin', [AdminOrganizationController::class, 'updateInitialAdmin'])
        ->middleware(['security.module:security', 'security.permission:security.auth.configure'])
        ->name('admin.organizations.initial-admin.update');
    Route::post('admin/organizations/{organization}/initial-admin/recover', [AdminOrganizationController::class, 'recoverInitialAdmin'])
        ->middleware(['security.module:security', 'security.permission:security.auth.configure'])
        ->name('admin.organizations.initial-admin.recover');
    Route::put('admin/organizations/{organization}/activate-production', [AdminOrganizationController::class, 'activateProduction'])
        ->middleware(['security.module:security', 'security.permission:security.auth.configure'])
        ->name('admin.organizations.activate-production');
    Route::put('admin/organizations/{organization}/suspend', [AdminOrganizationController::class, 'suspendOrganization'])
        ->middleware(['security.module:security', 'security.permission:security.auth.configure'])
        ->name('admin.organizations.suspend');
    Route::put('admin/organizations/{organization}/reactivate', [AdminOrganizationController::class, 'reactivateOrganization'])
        ->middleware(['security.module:security', 'security.permission:security.auth.configure'])
        ->name('admin.organizations.reactivate');

    Route::get('admin/settings', [CommerceSettingAdminController::class, 'edit'])
        ->middleware(['security.module:commerce', 'security.permission:commerce.settings.view'])
        ->name('admin.settings.edit');
    Route::put('admin/settings', [CommerceSettingAdminController::class, 'update'])
        ->middleware(['security.module:commerce', 'security.permission:commerce.settings.update'])
        ->name('admin.settings.update');

    Route::get('admin/inventory', [AdminInventoryController::class, 'index'])
        ->middleware(['security.module:inventory', 'security.permission:inventory.module.view'])
        ->name('admin.inventory.index');
    Route::get('admin/inventory/warehouses', [AdminInventoryController::class, 'warehouses'])
        ->middleware(['security.module:inventory', 'security.permission:inventory.warehouses.view'])
        ->name('admin.inventory.warehouses.index');

    Route::resource('admin/products', AdminProductController::class)
        ->middleware('security.module:catalog')
        ->names('admin.products');
    Route::post('admin/products/{product}/images', [AdminProductImageController::class, 'store'])
        ->middleware(['security.module:catalog', 'security.permission:catalog.products.update'])
        ->name('admin.products.images.store');
    Route::delete('admin/products/{product}/images/{image}', [AdminProductImageController::class, 'destroy'])
        ->middleware(['security.module:catalog', 'security.permission:catalog.products.update'])
        ->name('admin.products.images.destroy');

    Route::resource('admin/categories', AdminCategoryController::class)
        ->middleware('security.module:catalog')
        ->except(['show'])
        ->names('admin.categories');

    Route::resource('admin/unit-measures', AdminUnitMeasureController::class)
        ->middleware('security.module:catalog')
        ->except(['show'])
        ->names('admin.unit-measures');

    Route::get('admin/customers', [AdminCustomerController::class, 'index'])
        ->middleware(['security.module:customers', 'security.permission:customers.records.view'])
        ->name('admin.customers.index');
    Route::get('admin/customers/{customer}', [AdminCustomerController::class, 'show'])
        ->middleware(['security.module:customers', 'security.permission:customers.records.view'])
        ->name('admin.customers.show');
    Route::put('admin/customers/{customer}', [AdminCustomerController::class, 'update'])
        ->middleware(['security.module:customers', 'security.permission:customers.records.update'])
        ->name('admin.customers.update');

    Route::get('admin/orders', [AdminOrderController::class, 'index'])
        ->middleware(['security.module:sales', 'security.permission:sales.orders.view'])
        ->name('admin.orders.index');
    Route::get('admin/orders/{order}', [AdminOrderController::class, 'show'])
        ->middleware(['security.module:sales', 'security.permission:sales.orders.view'])
        ->name('admin.orders.show');
    Route::get('admin/orders/{order}/pdf', [AdminOrderController::class, 'downloadPdf'])
        ->middleware(['security.module:sales', 'security.permission:sales.orders.export'])
        ->name('admin.orders.download.pdf');
    Route::put('admin/orders/{order}', [AdminOrderController::class, 'update'])
        ->middleware(['security.module:sales', 'security.permission:sales.orders.update'])
        ->name('admin.orders.update');
});
