<?php

use Illuminate\Support\Facades\Route;
use Modules\Transport\Http\Controllers\TransportGuideController;
use Modules\Transport\Http\Controllers\TransportSettingController;

Route::middleware(['web', 'auth', 'tenant.capability:transport.gre', 'security.module:transport'])->prefix('admin/transport')->name('admin.transport.')->group(function (): void {
    Route::get('guides', [TransportGuideController::class, 'index'])->middleware('security.permission:transport.guides.view')->name('guides.index');
    Route::get('guides/create', [TransportGuideController::class, 'create'])->middleware('security.permission:transport.guides.create')->name('guides.create');
    Route::post('guides', [TransportGuideController::class, 'store'])->middleware('security.permission:transport.guides.create')->name('guides.store');
    Route::get('guides/{guide}', [TransportGuideController::class, 'show'])->middleware('security.permission:transport.guides.view')->name('guides.show');
    Route::post('guides/{guide}/submit', [TransportGuideController::class, 'submit'])->middleware('security.permission:transport.guides.submit')->name('guides.submit');
    Route::post('guides/{guide}/poll', [TransportGuideController::class, 'poll'])->middleware('security.permission:transport.guides.poll')->name('guides.poll');
    Route::get('guides/{guide}/xml', [TransportGuideController::class, 'downloadXml'])->middleware('security.permission:transport.guides.export')->name('guides.xml');
    Route::get('guides/{guide}/cdr', [TransportGuideController::class, 'downloadCdr'])->middleware('security.permission:transport.guides.export')->name('guides.cdr');
    Route::get('settings', [TransportSettingController::class, 'edit'])->middleware('security.permission:transport.settings.configure')->name('settings.edit');
    Route::put('settings', [TransportSettingController::class, 'update'])->middleware('security.permission:transport.settings.configure')->name('settings.update');
    Route::post('settings/validate', [TransportSettingController::class, 'validateCredentials'])->middleware('security.permission:transport.settings.configure')->name('settings.validate');
});
