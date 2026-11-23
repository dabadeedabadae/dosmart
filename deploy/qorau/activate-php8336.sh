#!/usr/bin/env bash
# Activate the separately built and validated PHP runtime on the shared host.
set -euo pipefail
cd /opt/dosmart
dc=(docker compose --env-file .env.production -f compose.onprem.yml)
old=dosmart-app:compactprint20261022
new=dosmart-app:php8336-20261023
test "$(docker inspect dosmart-app-1 --format '{{.Config.Image}}')" = "$old"
test "$(docker run --rm --network none --entrypoint php "$new" -r 'echo PHP_VERSION;')" = '8.3.36'
backup="/opt/dosmart/backups/php-security-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$backup"
chmod 700 "$backup"
cp compose.onprem.yml "$backup/compose.onprem.yml"
docker image inspect "$old" --format '{{.Id}}' > "$backup/previous-image-id.txt"
docker ps --format '{{.ID}} {{.Names}}' | sort > "$backup/before.txt"
"${dc[@]}" exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' > "$backup/database.sql"
chmod 600 "$backup/database.sql"
rollback() {
    cp "$backup/compose.onprem.yml" compose.onprem.yml
    "${dc[@]}" up -d --no-deps app scheduler
    "${dc[@]}" exec -T web nginx -s reload
    echo "ROLLED BACK. Backup: $backup"
}
trap rollback ERR
sed -i "s@image: $old@image: $new@" compose.onprem.yml
"${dc[@]}" up -d --no-deps app scheduler
"${dc[@]}" exec -T app php-fpm -t
"${dc[@]}" exec -T web nginx -t
"${dc[@]}" exec -T web nginx -s reload
for path in /shop /shop/cart /api/v1/categories /admin/login; do
    curl --retry 5 --retry-delay 2 --retry-all-errors -fsS -o /dev/null -H 'Host: dos-mart.kz' -H 'X-Forwarded-Proto: https' "http://127.0.0.1:8088$path"
done
code=$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: dos-mart.kz' -H 'X-Forwarded-Proto: https' http://127.0.0.1:8088/admin/orders)
test "$code" = 302
"${dc[@]}" exec -T app php -r 'if (PHP_VERSION !== "8.3.36" || ini_get("session.upload_progress.enabled")) exit(1); echo "Patched PHP and upload progress Off verified\n";'
docker ps --format '{{.ID}} {{.Names}}' | sort > "$backup/after.txt"
diff <(grep -v ' dosmart-' "$backup/before.txt") <(grep -v ' dosmart-' "$backup/after.txt")
trap - ERR
printf 'DEPLOY OK. Backup: %s\n' "$backup"
