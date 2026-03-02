<?php
use App\Http\Controllers\{ProductController,CategoryController,CartController,OrderController};
use App\Http\Controllers\{ContactoController};
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\ProductImageController as AdminProductImageController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\CatalogController;
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
Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalogo/{product:slug}', [CatalogController::class, 'show'])->name('catalog.show');

Route::get('/carrito', [CartController::class,'view'])->name('cart.view');
Route::post('/carrito/agregar/{product}', [CartController::class,'add'])->name('cart.add');
Route::post('/carrito/quitar/{product:id}', [CartController::class,'remove'])->name('cart.remove');
Route::post('/carrito/actualizar/{product:id}', [CartController::class,'update'])->name('cart.update');
Route::post('/carrito/vaciar', [CartController::class,'clear'])->name('cart.clear');

Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
});

Route::middleware('auth')->group(function () {
    Route::resource('admin/products', AdminProductController::class)->names('admin.products');
    Route::post('admin/products/{product}/images', [AdminProductImageController::class, 'store'])->name('admin.products.images.store');
    Route::delete('admin/products/{product}/images/{image}', [AdminProductImageController::class, 'destroy'])->name('admin.products.images.destroy');
    Route::get('/checkout', [OrderController::class,'showCheckout'])->name('checkout.show');
    Route::post('/checkout', [OrderController::class,'checkout'])->name('checkout.store');
    Route::get('/mis-pedidos', [OrderController::class,'myOrders'])->name('orders.mine');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
