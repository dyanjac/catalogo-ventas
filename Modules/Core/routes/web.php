<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactoController;
use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\ProductController;

Route::get('/', [ProductController::class, 'home'])->name('home');
Route::view('/nosotros', 'nosotros.index')->name('nosotros.index');
Route::get('/contacto', [ContactoController::class, 'index'])->name('contacto.index');

Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
