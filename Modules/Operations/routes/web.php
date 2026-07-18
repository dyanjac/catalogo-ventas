<?php

use Illuminate\Support\Facades\Route;
use Modules\Operations\Http\Controllers\OperationsController;

Route::middleware(['auth', 'security.module:operations'])->prefix('admin/operations')->name('admin.operations.')->group(function (): void {
    Route::get('/', [OperationsController::class, 'index'])->middleware('security.permission:operations.dashboard.view')->name('index');
    Route::get('/runs/{run}', [OperationsController::class, 'show'])->middleware('security.permission:operations.dashboard.view')->name('runs.show');
    Route::post('/runs', [OperationsController::class, 'run'])->middleware('security.permission:operations.reconciliations.run')->name('runs.store');
    Route::post('/incidents/{incident}/acknowledge', [OperationsController::class, 'acknowledge'])->middleware('security.permission:operations.incidents.manage')->name('incidents.acknowledge');
    Route::get('/metrics', [OperationsController::class, 'metrics'])->middleware('security.permission:operations.dashboard.view')->name('metrics');
});
