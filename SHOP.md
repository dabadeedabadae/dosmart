# Магазин DoSmart

Каталог: `/shop`. Покупатели: `/shop/login` (телефон и пароль). Сотрудники: `/admin/login` (логин и пароль).

Текущий сценарий пилота, API терминала, статусы, настройки Kaspi/WhatsApp, HTTPS, резервное копирование и запуск описаны в [PILOT.md](PILOT.md).

Для локальной разработки:

```sh
php artisan migrate
php artisan test
```

Во время разработки доступен отдельный просмотр на `http://127.0.0.1:8088/shop`, база `/tmp/dosmart-preview/shop.sqlite`. Основной `.env` не менялся. Повторный запуск, пока временная база существует:

```sh
APP_URL=http://127.0.0.1:8088 DB_CONNECTION=sqlite DB_DATABASE=/tmp/dosmart-preview/shop.sqlite DB_URL= SESSION_DRIVER=file CACHE_STORE=file php artisan serve --host=127.0.0.1 --port=8088
```

SMS-подтверждение и восстановление пароля пока не реализованы. Реальная оплата и возврат выполняются сотрудником в Kaspi Pay; сайт хранит заказ и его статус.
