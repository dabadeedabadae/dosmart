<?php

use App\Models\OrderDraft;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('drafts:prune', function () {
    $count = OrderDraft::whereNull('order_id')->where('expires_at', '<=', now())->delete();
    $this->info("Удалено черновиков: {$count}");
})->purpose('Удалить неоформленные корзины старше 4 дней');
Schedule::command('drafts:prune')->daily()->withoutOverlapping();

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
