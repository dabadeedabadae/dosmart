<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Shop\ShopController;

// Публичная витрина (для планшетов в учреждениях)
Route::prefix('shop')->name('shop.')->group(function () {
    Route::get('/', [ShopController::class, 'index'])->name('index');
    Route::get('/category/{category}', [ShopController::class, 'category'])->name('category');
    Route::get('/cart', [ShopController::class, 'cart'])->name('cart');
    Route::get('/login', [\App\Http\Controllers\Shop\AuthController::class, 'form'])->name('login');
    Route::get('/register', [\App\Http\Controllers\Shop\AuthController::class, 'form'])->name('register');
    Route::post('/login', [\App\Http\Controllers\Shop\AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [\App\Http\Controllers\Shop\AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::middleware('auth:customer')->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Shop\AuthController::class, 'logout'])->name('logout');
        Route::get('/orders', [\App\Http\Controllers\Shop\AuthController::class, 'orders'])->name('orders');
        Route::post('/orders', fn () => response()->json(['message'=>'Создайте корзину через /shop/drafts и оформите её по возвращённой ссылке.'], 410))->middleware('throttle:10,1')->name('orders.store');
        Route::get('/checkout', [ShopController::class, 'checkout'])->name('checkout');
    });
    Route::get('/success', [ShopController::class, 'success'])->name('success');
});

Route::get('/', fn() => redirect()->route('shop.index'));
Route::post('/shop/drafts', [\App\Http\Controllers\Shop\PilotController::class, 'create'])->middleware('throttle:15,1')->name('pilot.create');
Route::post('/o', [\App\Http\Controllers\Shop\PilotController::class, 'lookup'])->middleware('throttle:15,1')->name('pilot.lookup');
Route::get('/o/{code}', [\App\Http\Controllers\Shop\PilotController::class, 'show'])->middleware('throttle:30,1')->name('pilot.show');
Route::post('/o/{code}', [\App\Http\Controllers\Shop\PilotController::class, 'checkout'])->middleware('throttle:10,1')->name('pilot.checkout');
Route::get('/o/{code}/payment', [\App\Http\Controllers\Shop\PilotController::class, 'payment'])->middleware('throttle:30,1')->name('pilot.payment');
Route::view('/privacy', 'shop.privacy')->name('privacy');


Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'loginForm'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.post');
});

Route::post('/admin/logout', [AuthController::class, 'logout'])->middleware('auth:web')->name('admin.logout');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('categories', CategoryController::class)->except(['show']);

    Route::get('/report', [ReportController::class, 'index'])->name('report');
});
