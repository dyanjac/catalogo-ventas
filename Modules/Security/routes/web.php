<?php

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\AccessManagementController;
use Modules\Security\Http\Controllers\AdminLoginController;
use Modules\Security\Http\Controllers\AuthenticationSettingsController;
use Modules\Security\Http\Controllers\ModulePlaceholderController;

Route::middleware('guest')->group(function () {
    Route::get('admin/login', [AdminLoginController::class, 'create'])->name('admin.login');
});

Route::middleware(['auth', 'security.module:security'])->prefix('admin/security')->name('admin.security.')->group(function () {
    Route::get('authentication', [AuthenticationSettingsController::class, 'edit'])
        ->middleware('security.permission:security.auth.view')
        ->name('authentication.edit');

    Route::get('roles', [AccessManagementController::class, 'roles'])
        ->middleware('security.permission:security.roles.view')
        ->name('roles.index');

    Route::get('users', [AccessManagementController::class, 'users'])
        ->middleware('security.permission:security.users.view')
        ->name('users.index');

    Route::get('branches', [AccessManagementController::class, 'branches'])
        ->middleware('security.permission:security.branches.view')
        ->name('branches.index');

    Route::get('audit', [AccessManagementController::class, 'audit'])
        ->middleware('security.permission:security.audit.view')
        ->name('audit.index');
});

Route::middleware(['auth'])->group(function () {
    Route::get('admin/modules/{module:code}', [ModulePlaceholderController::class, 'show'])
        ->name('admin.modules.placeholder');
});
