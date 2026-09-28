#!/bin/sh
set -eu
umask 077
mkdir -p /backups
backup_file="/backups/dosmart-$(date -u +%Y%m%dT%H%M%SZ).dump"
trap 'rm -f "$backup_file.partial"' EXIT
pg_dump --format=custom --file="$backup_file.partial"
pg_restore --list "$backup_file.partial" >/dev/null
mv "$backup_file.partial" "$backup_file"
# Only complete backups created by this script are subject to retention.
find /backups -name 'dosmart-*.dump' -type f -mtime +14 -delete
printf 'Backup completed: %s\n' "$backup_file"
