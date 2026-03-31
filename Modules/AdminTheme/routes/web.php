<?php

use Illuminate\Support\Facades\Route;
use Modules\AdminTheme\Http\Controllers\AdminThemeController;

Route::middleware(['auth', 'security.module:admin_theme'])->group(function () {
    Route::get('admin/theme', [AdminThemeController::class, 'edit'])
        ->middleware('security.permission:admin_theme.palette.view')
        ->name('admin.theme.edit');
    Route::put('admin/theme', [AdminThemeController::class, 'update'])
        ->middleware('security.permission:admin_theme.palette.update')
        ->name('admin.theme.update');
    Route::delete('admin/theme', [AdminThemeController::class, 'reset'])
        ->middleware('security.permission:admin_theme.palette.update')
        ->name('admin.theme.reset');
});
