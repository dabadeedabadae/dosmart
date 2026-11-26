<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Carbon::setLocale('ru');
        Paginator::defaultView('admin.pagination');

        // Планшеты учреждения часто выходят в сеть с одного IP — считаем запросы по каждому планшету
        // и каждому действию отдельно. Перебор пароля дополнительно ограничен блокировкой карточки.
        RateLimiter::for('prisoner-tablet', function (Request $request) {
            $tablet = substr(hash('sha256', (string) $request->header('X-Dosmart-Tablet')), 0, 16);

            return Limit::perMinute(30)->by($request->route()?->uri().'|'.$request->ip().'|'.$tablet);
        });
    }
}
