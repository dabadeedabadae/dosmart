# DoSmart на общей машине Qorau

Проверено 13 октября 2026 года. SSH: `ubuntu@100.113.110.13`, проект `/opt/dosmart`, адрес `https://dos-mart.kz`.

Сначала прочитайте [правила общей машины](QORAU-SHARED-HOST.md), полученные из `/opt/mdm/src/docs/QORAU-SHARED-HOST.md`. DoSmart использует только свои сети `dosmart_frontend`, `dosmart_backend`, БД и тома. Web публикует `127.0.0.1:8088`. Отдельный `edge_nginx` направляет сюда домен. Конфигурации `/opt/mdm`, `/opt/edge`, контейнеры MDM и общий Docker при обновлении магазина не менять и не перезапускать. Старый файл `nginx-edge.conf` в этой папке больше не применять: он относится к прежней схеме.

## Запуск и проверка

```sh
cd /opt/dosmart
docker compose --env-file .env.production -f compose.onprem.yml up -d web
docker compose --env-file .env.production -f compose.onprem.yml ps
docker compose --env-file .env.production -f compose.onprem.yml logs --tail=80 app web
```

Копия фактического Compose-файла сохранена рядом. Сейчас образ `dosmart-app:additions20261013` (обновлены редактор заявки, формат телефона и доставка на следующий день за 3000 ₸), построенный на прежнем `dosmart-app:migrate20261010` с обновлёнными PHP-файлами и шаблонами; зависимости не изменялись. Следующие образы можно собрать из актуальных исходников через Dockerfile.production. Не использовать здесь docker-compose.production.yml с Caddy.

## Режим обслуживания и обновление

В `.env.production` настроены `APP_MAINTENANCE_DRIVER=cache` и `APP_MAINTENANCE_STORE=database`. Флаг хранится в PostgreSQL и сохраняется при пересоздании контейнера; это проверено пересозданием app (503 до команды up).

```sh
docker compose --env-file .env.production -f compose.onprem.yml exec -T app php artisan down --retry=60
# После бэкапа, подготовки и проверки нового образа: изменить его тег в compose.
docker compose --env-file .env.production -f compose.onprem.yml up -d --no-deps app web
docker compose --env-file .env.production -f compose.onprem.yml exec -T app php artisan migrate --force
docker compose --env-file .env.production -f compose.onprem.yml exec -T app php artisan view:clear
docker compose --env-file .env.production -f compose.onprem.yml exec -T app php artisan config:clear
docker compose --env-file .env.production -f compose.onprem.yml exec -T app php artisan up
docker compose --env-file .env.production -f compose.onprem.yml up -d --no-deps scheduler backup
```

Не выполнять `cache:clear` или `optimize:clear` во время обслуживания: они очищают и хранилище его флага. Не запускать сидеры на рабочей базе. `.env.production`, APP_KEY и тома сохранять. Миграции предварительно проверять на копии БД.

## Бэкапы и откат

Бэкап обновления: `/opt/dosmart/backups/update-20261013/`: `host-files.tar.gz`, `active-code.tar.gz`, `uploads.tar.gz`, `database-before-switch.dump`, прежний Compose. Старый образ сохранён. Перед восстановлением базы остановить запись и сохранить текущую базу: восстановление старого дампа потеряет новые заявки. Откат проверять по совместимости схемы, не запускать migrate:rollback автоматически.

Сервис backup работает ежедневно, хранит дампы 14 дней. Загрузки нужно отдельно архивировать; резервные копии также следует выносить с машины. Лимиты памяти контейнеров магазина суммарно 1408 MiB. Для работающей БД лимит применён через docker update без пересоздания.

## Старый адрес и приложение

APK 1.0.6 (код 7) собран с `DOSMART_BASE_URL=https://dos-mart.kz`. Старый APK 1.0.5 использует `https://dosmart.aican.cloud`: это другая база, данное обновление её не затрагивало. Перед объединением баз проверить новые заявки на старом сервере, не затирать их старым дампом.
