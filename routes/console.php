<?php

use App\Models\OrderDraft;
use App\Models\Prisoner;
use App\Models\User;
use App\Services\PrisonerAccounts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('drafts:prune', function () {
    $count = OrderDraft::whereNull('order_id')->where('expires_at', '<=', now())->delete();
    $this->info("Удалено черновиков: {$count}");
})->purpose('Удалить неоформленные корзины старше 4 дней');
Schedule::command('drafts:prune')->daily()->withoutOverlapping();

// Каждый день запоминаем лимит текущего месяца у всех, у кого лимит включён.
// Тогда перенос остатка считается по лимиту того месяца, даже если человек не заходил в кабинет.
Artisan::command('limits:snapshot', function (PrisonerAccounts $accounts) {
    $count = 0;
    Prisoner::with('institution')->where('spending_mode', '!=', 'unlimited')
        ->chunkById(200, function ($prisoners) use ($accounts, &$count) {
            foreach ($prisoners as $prisoner) {
                $accounts->limitState($prisoner);
                $count++;
            }
        });
    $this->info("Лимиты сохранены: {$count}");
})->purpose('Сохранить лимит текущего месяца для переноса остатка');
Schedule::command('limits:snapshot')->dailyAt('00:10')->timezone('Asia/Almaty')->withoutOverlapping();

Artisan::command('admin:create {username}', function () {
    $username = $this->argument('username');
    if (! preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
        $this->error('Логин: 3–50 латинских букв, цифр, _, -, .');

        return 1;
    }
    if (User::where('username', $username)->exists()) {
        $this->error('Этот логин уже существует.');

        return 1;
    }
    $password = $this->secret('Пароль (не менее 12 символов)');
    if (strlen((string) $password) < 12) {
        $this->error('Пароль слишком короткий.');

        return 1;
    }
    if ($password !== $this->secret('Повторите пароль')) {
        $this->error('Пароли не совпадают.');

        return 1;
    }
    User::create(['name' => $username, 'username' => $username, 'email' => $username.'@staff.invalid', 'password' => $password]);
    $this->info('Администратор создан. Вход: /admin/login');
})->purpose('Создать сотрудника без публичной регистрации');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
