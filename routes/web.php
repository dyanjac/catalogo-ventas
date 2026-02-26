<?php
use App\Http\Controllers\{ProductController,CategoryController,CartController,OrderController};
use App\Http\Controllers\{ContactoController};
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

 
Route::get('/greeting', function () {
    return 'Hello World';
});


Route::get('/', [ProductController::class,'home'])->name('home');

Route::get('/productos', [ProductController::class,'index'])->name('products.index');

Route::get('/contacto', [ContactoController::class,'index'])->name('contacto.index');

Route::get('/producto/{product:slug}', [ProductController::class,'show'])->name('products.show');
Route::get('/categoria/{category:slug}', [CategoryController::class,'show'])->name('categories.show');

Route::get('/carrito', [CartController::class,'view'])->name('cart.view');
Route::post('/carrito/agregar/{product}', [CartController::class,'add'])->name('cart.add');
Route::post('/carrito/quitar/{product}', [CartController::class,'remove'])->name('cart.remove');
Route::post('/carrito/actualizar/{product}', [CartController::class,'update'])->name('cart.update');
Route::post('/carrito/vaciar', [CartController::class,'clear'])->name('cart.clear');

Route::middleware('auth')->group(function () {
    Route::post('/checkout', [OrderController::class,'checkout'])->name('checkout');
    Route::get('/mis-pedidos', [OrderController::class,'myOrders'])->name('orders.mine');
});

