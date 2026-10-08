<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\InstitutionController;

Route::post('/v1/drafts', [\App\Http\Controllers\Shop\PilotController::class, 'create'])->middleware('throttle:30,1');

// Public guest baskets have the same access level as the website checkout.
Route::post('/v1/guest/drafts', [\App\Http\Controllers\Shop\PilotController::class, 'create'])->middleware('throttle:15,1')->name('guest.drafts');

// Публичные маршруты (без авторизации)
Route::prefix('v1')->group(function () {

    // Каталог
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/new-arrivals', [ProductController::class, 'newArrivals']);

    // Учреждения
    Route::get('/institutions', [InstitutionController::class, 'index']);

    // Заказы
    Route::post('/orders', fn () => response()->json(['message'=>'Используйте POST /api/v1/drafts и ссылку оформления из ответа.'], 410))->middleware(['web', 'auth:customer', 'throttle:10,1']);
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show'])->middleware(['web', 'auth:customer']);

    // Kaspi Pay webhook — ВРЕМЕННО ОТКЛЮЧЁН.
    // Интеграция с Kaspi Pay ещё не готова, а метод kaspiWebhook в контроллере не проверяет
    // подлинность запроса (нет проверки подписи/секрета от Kaspi). Открытый роут на этот
    // метод позволил бы кому угодно помечать чужие заказы оплаченными без реальной оплаты.
    // Раскомментировать только после того, как в OrderController::kaspiWebhook добавлена
    // проверка подписи/секрета/IP, которую даёт Kaspi для вашего мерчант-аккаунта.
    // Route::post('/payments/kaspi/webhook', [OrderController::class, 'kaspiWebhook']);
});

// Mobile terminal: validate its existing user session on the trusted identity server.
Route::middleware(['throttle:30,1', \App\Http\Middleware\AuthenticateTerminalSession::class])->group(function () {
    Route::post('/v1/terminal/orders', [\App\Http\Controllers\Api\TerminalOrderController::class, 'store']);
    Route::post('/v1/terminal/drafts', [\App\Http\Controllers\Shop\PilotController::class, 'create']);
    Route::post('/v1/terminal/drafts/{code}/sent', [\App\Http\Controllers\Shop\PilotController::class, 'acknowledge']);
});
