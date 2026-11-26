<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\InstitutionController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderReportController;
use App\Http\Controllers\Admin\PrisonerController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Shop\ConsumerHelpController;
use App\Http\Controllers\Shop\FamilyController;
use App\Http\Controllers\Shop\PilotController;
use App\Http\Controllers\Shop\ShopController;
use Illuminate\Support\Facades\Route;

// Публичная витрина (для планшетов в учреждениях)
Route::prefix('shop')->name('shop.')->group(function () {
    Route::get('/', [ShopController::class, 'index'])->name('index');
    Route::get('/category/{category}', [ShopController::class, 'category'])->name('category');
    Route::get('/cart', [ShopController::class, 'cart'])->name('cart');
    // Вход и регистрация покупателей отключены: заказ оформляется без аккаунта. Вход только для сотрудников — /admin/login.
    Route::redirect('/login', '/shop')->name('login');
    Route::redirect('/register', '/shop')->name('register');
    Route::middleware('auth:customer')->group(function () {
        Route::post('/logout', [App\Http\Controllers\Shop\AuthController::class, 'logout'])->name('logout');
        Route::get('/orders', [App\Http\Controllers\Shop\AuthController::class, 'orders'])->name('orders');
        Route::post('/orders', fn () => response()->json(['message' => 'Создайте корзину через /shop/drafts и оформите её по возвращённой ссылке.'], 410))->middleware('throttle:10,1')->name('orders.store');
        Route::get('/checkout', [ShopController::class, 'checkout'])->name('checkout');
    });
    Route::get('/success', [ShopController::class, 'success'])->name('success');
});

Route::get('/', fn () => redirect()->route('shop.index'));
Route::post('/shop/drafts', [PilotController::class, 'create'])->middleware('throttle:15,1')->name('pilot.create');
Route::post('/o', [PilotController::class, 'lookup'])->middleware('throttle:15,1')->name('pilot.lookup');
Route::get('/o/{code}', [PilotController::class, 'show'])->middleware('throttle:30,1')->name('pilot.show');
Route::post('/o/{code}', [PilotController::class, 'checkout'])->middleware('throttle:10,1')->name('pilot.checkout');
// Родственник видит только лимиты получателя (не баланс): ФИО + учреждение + дата рождения.
Route::post('/o/{code}/recipient-limits', [PilotController::class, 'recipientLimits'])->middleware('throttle:30,1')->name('pilot.recipient-limits');
Route::get('/o/{code}/payment', [PilotController::class, 'payment'])->middleware('throttle:30,1')->name('pilot.payment');
Route::post('/o/{code}/payment-reported', [PilotController::class, 'reportPayment'])->middleware('throttle:10,1')->name('pilot.report-payment');
Route::post('/o/{code}/changes', [PilotController::class, 'requestChanges'])->middleware('throttle:10,1')->name('pilot.changes');
Route::get('/o/{code}/status', [PilotController::class, 'status'])->middleware('throttle:30,1')->name('pilot.status');
Route::get('/o/{code}/receipt', [PilotController::class, 'receipt'])->middleware('throttle:30,1')->name('pilot.receipt');
Route::get('/help/{topic?}', [ConsumerHelpController::class, 'show'])->name('shop.help');
Route::get('/help/documents/consumer-guide', [ConsumerHelpController::class, 'download'])->name('shop.help.download');
Route::view('/privacy', 'shop.privacy')->name('privacy');
Route::controller(FamilyController::class)->prefix('family')->name('family.')->group(function () {
    Route::get('/', 'form')->name('form');
    Route::post('/', 'enter')->middleware('throttle:10,1')->name('enter');
    Route::get('/balance', 'show')->middleware('throttle:30,1')->name('show');
    Route::post('/deposit', 'deposit')->middleware('throttle:5,1')->name('deposit');
    Route::post('/logout', 'logout')->name('logout');
});

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'loginForm'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'login'])->name('admin.login.post');
});

Route::post('/admin/logout', [AuthController::class, 'logout'])->middleware('auth:web')->name('admin.logout');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/report', [OrderReportController::class, 'index'])->name('orders.report');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');
    Route::post('/orders/{order}/changes/{change}', [OrderController::class, 'reviewChanges'])->name('orders.changes');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::patch('/institutions/{institution}/activity', [InstitutionController::class, 'updateActivity'])->name('institutions.activity');
    Route::resource('institutions', InstitutionController::class)->except(['show']);

    Route::controller(PrisonerController::class)->group(function () {
        Route::get('/prisoners', 'index')->name('prisoners.index');
        Route::get('/prisoners/people', 'people')->name('prisoners.people');
        Route::get('/prisoners/requests', 'requests')->name('prisoners.requests');
        Route::post('/prisoners/requests/{depositRequest}/credit', 'creditRequest')->name('prisoners.requests.credit');
        Route::post('/prisoners/requests/{depositRequest}/reject', 'rejectRequest')->name('prisoners.requests.reject');
        Route::get('/prisoners/create', 'create')->name('prisoners.create');
        Route::post('/prisoners', 'store')->name('prisoners.store');
        Route::get('/prisoners/{prisoner}', 'show')->name('prisoners.show');
        Route::patch('/prisoners/{prisoner}', 'update')->name('prisoners.update');
        Route::post('/prisoners/{prisoner}/transfers', 'register')->name('prisoners.transfers.store');
        Route::post('/prisoners/{prisoner}/transfers/{transfer}', 'settle')->name('prisoners.transfers.settle');
        Route::post('/prisoners/{prisoner}/deposit', 'deposit')->name('prisoners.deposit');
        Route::post('/prisoners/{prisoner}/unlock', 'unlock')->name('prisoners.unlock');
        Route::post('/prisoners/{prisoner}/review', 'review')->name('prisoners.review');
        Route::post('/prisoners/{prisoner}/reset-registration', 'resetRegistration')->name('prisoners.reset-registration');
        Route::post('/prisoners/{prisoner}/family-code/disable', 'disableFamilyCode')->name('prisoners.family-code.disable');
        Route::get('/prisoners/{prisoner}/transfers/{transfer}/receipt', 'transferReceipt')->name('prisoners.transfers.receipt');
        Route::post('/institutions/{institution}/tablet-code', 'tabletCode')->name('institutions.tablet-code');
        Route::post('/institutions/{institution}/import', 'import')->name('institutions.import');
        Route::patch('/institutions/{institution}/spending-limits', 'spendingLimits')->name('institutions.spending-limits');
        Route::patch('/institutions/{institution}/parcel-limits', 'limits')->name('institutions.parcel-limits');
    });

    Route::get('/report', [ReportController::class, 'index'])->name('report');
});
