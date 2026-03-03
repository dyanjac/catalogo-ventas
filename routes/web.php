<?php
use App\Http\Controllers\{ProductController,CategoryController,CartController,OrderController};
use App\Http\Controllers\{ContactoController};
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ProductImageController as AdminProductImageController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\UnitMeasureController as AdminUnitMeasureController;
use App\Http\Controllers\CatalogController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

 
Route::get('/greeting', function () {
    return 'Hello World';
});


Route::get('/', [ProductController::class,'home'])->name('home');

Route::get('/productos', [ProductController::class,'index'])->name('products.index');

Route::view('/nosotros', 'nosotros.index')->name('nosotros.index');
Route::get('/contacto', [ContactoController::class,'index'])->name('contacto.index');

Route::get('/producto/{product:slug}', [ProductController::class,'show'])->name('products.show');
Route::get('/categoria/{category:slug}', [CategoryController::class,'show'])->name('categories.show');
Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalogo/{product:slug}', [CatalogController::class, 'show'])->name('catalog.show');

Route::get('/carrito', [CartController::class,'view'])->name('cart.view');
Route::post('/carrito/agregar/{product:id}', [CartController::class,'add'])->name('cart.add');
Route::post('/carrito/quitar/{product:id}', [CartController::class,'remove'])->name('cart.remove');
Route::post('/carrito/actualizar/{product:id}', [CartController::class,'update'])->name('cart.update');
Route::post('/carrito/vaciar', [CartController::class,'clear'])->name('cart.clear');

Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
});

Route::middleware('auth')->group(function () {
    Route::get('/checkout', [OrderController::class,'showCheckout'])->name('checkout.show');
    Route::post('/checkout', [OrderController::class,'checkout'])->name('checkout.store');
    Route::get('/mis-pedidos', [OrderController::class,'myOrders'])->name('orders.mine');
    Route::get('/mis-pedidos/{order}', [OrderController::class,'show'])->name('orders.show');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', EnsureSuperAdmin::class])->group(function () {
    Route::get('admin', AdminDashboardController::class)->name('admin.dashboard');
    Route::resource('admin/products', AdminProductController::class)->names('admin.products');
    Route::post('admin/products/{product}/images', [AdminProductImageController::class, 'store'])->name('admin.products.images.store');
    Route::delete('admin/products/{product}/images/{image}', [AdminProductImageController::class, 'destroy'])->name('admin.products.images.destroy');

    Route::resource('admin/categories', AdminCategoryController::class)
        ->except(['show'])
        ->names('admin.categories');

    Route::resource('admin/unit-measures', AdminUnitMeasureController::class)
        ->except(['show'])
        ->names('admin.unit-measures');

    Route::get('admin/customers', [AdminCustomerController::class, 'index'])->name('admin.customers.index');
    Route::get('admin/customers/{customer}', [AdminCustomerController::class, 'show'])->name('admin.customers.show');
    Route::put('admin/customers/{customer}', [AdminCustomerController::class, 'update'])->name('admin.customers.update');

    Route::get('admin/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
    Route::get('admin/orders/{order}', [AdminOrderController::class, 'show'])->name('admin.orders.show');
    Route::put('admin/orders/{order}', [AdminOrderController::class, 'update'])->name('admin.orders.update');
});
