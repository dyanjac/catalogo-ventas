<?php

use Illuminate\Support\Facades\Route;
use Modules\Subscriptions\Http\Controllers\SubscriptionController;

Route::middleware(['web', 'auth', 'tenant.capability:subscriptions.recurring', 'security.module:subscriptions'])
    ->prefix('admin/subscriptions')->name('admin.subscriptions.')->group(function (): void {
        Route::get('/', [SubscriptionController::class, 'index'])->middleware('security.permission:subscriptions.contracts.view')->name('index');
        Route::get('/create', [SubscriptionController::class, 'create'])->middleware('security.permission:subscriptions.contracts.create')->name('create');
        Route::post('/', [SubscriptionController::class, 'store'])->middleware('security.permission:subscriptions.contracts.create')->name('store');
        Route::get('/{subscription}', [SubscriptionController::class, 'show'])->middleware('security.permission:subscriptions.contracts.view')->name('show');
        Route::post('/{subscription}/renew', [SubscriptionController::class, 'renew'])->middleware('security.permission:subscriptions.contracts.process')->name('renew');
        Route::post('/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->middleware('security.permission:subscriptions.contracts.cancel')->name('cancel');
        Route::post('/{subscription}/adjust', [SubscriptionController::class, 'adjust'])->middleware('security.permission:subscriptions.contracts.adjust')->name('adjust');
        Route::post('/{subscription}/periods/{period}/billing', [SubscriptionController::class, 'attachBilling'])->middleware('security.permission:subscriptions.contracts.process')->name('billing.attach');
    });
