<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminTheme\Http\Controllers\AdminThemeController;
use Modules\Core\Http\Middleware\EnsureSuperAdmin;

Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('admin/theme', [AdminThemeController::class, 'edit'])->name('admin.theme.edit');
    Route::put('admin/theme', [AdminThemeController::class, 'update'])->name('admin.theme.update');
});
