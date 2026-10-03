<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\SaasRegistrationController;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\MarketingHomeController;
use Modules\Core\Http\Controllers\MarketingSeoController;

Route::get('/', MarketingHomeController::class)->name('home');
Route::get('/robots.txt', [MarketingSeoController::class, 'robots'])->name('marketing.robots');
Route::get('/sitemap.xml', [MarketingSeoController::class, 'sitemap'])->name('marketing.sitemap');
Route::view('/nosotros', 'nosotros.index')->name('nosotros.index');
Route::get('/contacto', [ContactoController::class, 'index'])->name('contacto.index');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::get('/saas/register', [SaasRegistrationController::class, 'create'])->name('saas.register.create');
    Route::post('/saas/register', [SaasRegistrationController::class, 'store'])->name('saas.register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
