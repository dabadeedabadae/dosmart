# DOSMART market — бриф для Claude Code

Макеты (5 экранов админки + 2 экрана мобильного приложения) лежат в этой папке как PNG.
Живой канвас с возможностью что-то поправить вручную: https://claude.ai/code/artifact/876849c9-6187-4714-94af-bbe9d3a94b24

## Файлы

| Файл | Экран | Реализовать как |
|---|---|---|
| 01-products-list.png | Список товаров | `GET /admin/products` — Blade-таблица |
| 02-product-form.png | Добавление/редактирование товара | `GET/POST /admin/products/{id?}/edit` |
| 03-categories.png | Категории | `GET /admin/categories` |
| 04-orders.png | Заказы | `GET /admin/orders` |
| 05-report.png | Отчёт | `GET /admin/report` |
| 06-mobile-home.png | Существующий главный экран приложения (для контекста стиля) | не трогать — просто референс стиля |
| 07-mobile-shop.png | Новая вкладка «Магазин» в мобильном приложении | каталог товаров в мобильном клиенте |

## Стек

Laravel + Blade (без Filament), PostgreSQL. Админка — серверный рендеринг, без отдельной сборки JS.
Для вкладки «Магазин» в мобильном приложении — отдаётся тем же Laravel через `routes/api.php` (Sanctum) в JSON, верстается уже в самом мобильном приложении.

## Дизайн-токены — админка (01–05)

Шрифт: Google Fonts `Plus Jakarta Sans`, веса 400–800.

```css
--bg: oklch(97.3% 0.006 255);
--surface: oklch(100% 0 0);
--surface-sunken: oklch(96% 0.006 255);
--border: oklch(90% 0.007 255);
--text: oklch(23% 0.02 255);
--text-secondary: oklch(48% 0.015 255);
--text-muted: oklch(63% 0.012 255);
--accent: oklch(53% 0.17 258);       /* основной синий */
--accent-hover: oklch(46% 0.17 258);
--accent-wash: oklch(94% 0.035 258); /* подложка активного пункта меню/бейджа */
--success-text: oklch(38% 0.12 150); --success-wash: oklch(94% 0.05 150);  /* "Оплачено" */
--warning-text: oklch(42% 0.11 70);  --warning-wash: oklch(95% 0.06 85);  /* "Ожидает оплаты" */
--danger-text: oklch(42% 0.16 25);   --danger-wash: oklch(95% 0.045 25);  /* удаление, "Отменён" */
```

Радиусы: карточки/кнопки 9–14px, аватар/точки — круг. Тени — едва заметные (`0 1px 2px rgb(0 0 0 / 4%)`), обводка `1px solid var(--border)` важнее тени.

## Дизайн-токены — мобильное приложение (06–07)

Шрифт: Google Fonts `Golos Text`, веса 400–800 (под кириллицу, совпадает с существующим приложением).

```css
--ink: oklch(18% 0.015 260);
--gray: oklch(56% 0.012 260);
--mint-bg: oklch(95% 0.035 165); --mint-border: oklch(72% 0.08 165); --mint-text: oklch(36% 0.09 165);
--mint-solid: oklch(58% 0.1 165); /* кнопка "+", бейдж корзины */
--yellow: oklch(85% 0.16 95); --navy: oklch(28% 0.09 275); /* логотип */
```

`oklch()` понимают все современные браузеры и Tailwind v4 «из коробки». Если нужен hex — прогнать через любой oklch→hex конвертер.

## Как отдать это Claude Code

1. Положите эту папку (`design/` со всеми PNG и этим файлом) в корень вашего Laravel-проекта.
2. Откройте терминал в проекте и запустите `claude`.
3. Дайте примерно такой промпт:

> Посмотри design/DESIGN.md и макеты design/*.png. Реализуй Laravel-админку (Blade, без Filament, PostgreSQL): миграции для products, categories, orders, order_items; роуты и контроллеры под admin/products, admin/categories, admin/orders, admin/report; Blade-вьюхи, максимально точно повторяющие макеты — расположение, отступы, цвета и шрифт из DESIGN.md. Начни с миграций и моделей, потом продукты (список + форма), потом категории, заказы, отчёт.

Claude Code сам откроет и «посмотрит» на PNG — можно просто указать путь, вставлять руками не нужно. Дальше уже точечно просите поправить: "сделай отступы как на 01-products-list.png точнее" и т.д.
