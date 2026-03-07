<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\CartController;
use Modules\Catalog\Http\Controllers\CatalogController;
use Modules\Catalog\Http\Controllers\CategoryController;
use Modules\Catalog\Http\Controllers\ProductController;

Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
Route::get('/producto/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/categoria/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalogo/{product:slug}', [CatalogController::class, 'show'])->name('catalog.show');

Route::get('/carrito', [CartController::class, 'view'])->name('cart.view');
Route::get('/carrito/agregar/{product:id}', [CartController::class, 'addFromLink'])->name('cart.add.link');
Route::post('/carrito/agregar/{product:id}', [CartController::class, 'add'])->name('cart.add');
Route::post('/carrito/quitar/{product:id}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/carrito/actualizar/{product:id}', [CartController::class, 'update'])->name('cart.update');
Route::post('/carrito/vaciar', [CartController::class, 'clear'])->name('cart.clear');
