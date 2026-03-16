<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\EnsureSuperAdmin;
use Modules\Security\Http\Controllers\AdminLoginController;
use Modules\Security\Http\Controllers\AuthenticationSettingsController;

Route::middleware('guest')->group(function () {
    Route::get('admin/login', [AdminLoginController::class, 'create'])->name('admin.login');
});

Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('admin/security/authentication', [AuthenticationSettingsController::class, 'edit'])->name('admin.security.authentication.edit');
});
