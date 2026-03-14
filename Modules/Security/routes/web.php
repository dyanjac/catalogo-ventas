<?php

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\AdminLoginController;

Route::middleware('guest')->group(function () {
    Route::get('admin/login', [AdminLoginController::class, 'create'])->name('admin.login');
});
