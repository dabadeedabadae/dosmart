<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\GuestOrderController;
use App\Http\Controllers\Api\InstitutionController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PrisonerAccountController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TerminalOrderController;
use App\Http\Controllers\Shop\PilotController;
use App\Http\Middleware\AuthenticateTerminalSession;
use App\Http\Middleware\RequirePrisonerTerminal;
use Illuminate\Support\Facades\Route;

Route::post('/v1/drafts', [PilotController::class, 'create'])->middleware('throttle:30,1');

// Public guest baskets have the same access level as the website checkout.
Route::post('/v1/guest/drafts', [PilotController::class, 'create'])->middleware('throttle:15,1')->name('guest.drafts');

Route::post('/v1/guest/orders', [GuestOrderController::class, 'store'])->middleware('throttle:10,1');

// Публичные маршруты (без авторизации)
Route::prefix('v1')->group(function () {

    // Каталог
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/new-arrivals', [ProductController::class, 'newArrivals']);

    // Учреждения
    Route::get('/institutions', [InstitutionController::class, 'index']);

    // Заказы
    Route::post('/orders', fn () => response()->json(['message' => 'Используйте POST /api/v1/drafts и ссылку оформления из ответа.'], 410))->middleware(['web', 'auth:customer', 'throttle:10,1']);
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show'])->middleware(['web', 'auth:customer']);

    // Kaspi Pay webhook — ВРЕМЕННО ОТКЛЮЧЁН.
    // Интеграция с Kaspi Pay ещё не готова, а метод kaspiWebhook в контроллере не проверяет
    // подлинность запроса (нет проверки подписи/секрета от Kaspi). Открытый роут на этот
    // метод позволил бы кому угодно помечать чужие заказы оплаченными без реальной оплаты.
    // Раскомментировать только после того, как в OrderController::kaspiWebhook добавлена
    // проверка подписи/секрета/IP, которую даёт Kaspi для вашего мерчант-аккаунта.
    // Route::post('/payments/kaspi/webhook', [OrderController::class, 'kaspiWebhook']);
});

Route::prefix('v1')->middleware(RequirePrisonerTerminal::class)->controller(PrisonerAccountController::class)->group(function () {
    Route::post('/tablet/setup', 'setup')->middleware('throttle:5,1');
    Route::get('/tablet', 'tablet')->middleware('throttle:prisoner-tablet');
    Route::prefix('prisoner')->group(function () {
        Route::get('/squads', 'squads')->middleware('throttle:prisoner-tablet');
        Route::post('/find', 'find')->middleware('throttle:prisoner-tablet');
        Route::post('/register', 'register')->middleware('throttle:prisoner-tablet');
        Route::post('/login', 'login')->middleware('throttle:prisoner-tablet');
        Route::get('/profile', 'profile')->middleware('throttle:prisoner-tablet');
        Route::get('/history', 'history')->middleware('throttle:prisoner-tablet');
        Route::post('/purchases', 'purchase')->middleware('throttle:prisoner-tablet');
        Route::post('/credentials', 'credentials')->middleware('throttle:prisoner-tablet');
        Route::post('/recover', 'recover')->middleware('throttle:prisoner-tablet');
        Route::post('/recovery', 'recoverySettings')->middleware('throttle:prisoner-tablet');
        Route::post('/squad', 'squad')->middleware('throttle:prisoner-tablet');
        Route::post('/family-code', 'familyCode')->middleware('throttle:prisoner-tablet');
        Route::post('/family-code/disable', 'disableFamilyCode')->middleware('throttle:prisoner-tablet');
    });
});

// Mobile terminal: validate its existing user session on the trusted identity server.
Route::middleware(['throttle:30,1', AuthenticateTerminalSession::class])->group(function () {
    Route::post('/v1/terminal/orders', [TerminalOrderController::class, 'store']);
    Route::post('/v1/terminal/drafts', [PilotController::class, 'create']);
    Route::post('/v1/terminal/drafts/{code}/sent', [PilotController::class, 'acknowledge']);
});
