#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
[[ -n "${CODESPACE_NAME:-}" ]] || { echo 'Use START.cmd for a fresh Windows installation.'; exit 1; }
docker compose exec -T web true
mkdir -p courses backups
# Build first, so a failed image build does not stop the current service.
docker compose build web
cli() { docker compose exec -T -u www-data web php "/var/www/html/admin/cli/$1" "${@:2}"; }
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
umask 077
backup="backups/before-$stamp"
mkdir -p "$backup"
cli maintenance.php --enable
docker compose --profile whisper stop cron processor whisper
docker compose exec -T db sh -c 'mariadb-dump --single-transaction --quick -uroot -p"$MARIADB_ROOT_PASSWORD" moodle' | gzip > "$backup/database.sql.gz"
docker compose exec -T web tar -C /var/moodledata --exclude=./cache --exclude=./localcache --exclude=./temp --exclude=./sessions -czf - . > "$backup/moodledata.tar.gz"
test -s "$backup/database.sql.gz"
gzip -t "$backup/database.sql.gz"
tar -tzf "$backup/moodledata.tar.gz" >/dev/null
docker compose stop web
# No volume removal. Recreate services with the new code and media mount.
docker compose run --rm --no-deps init
docker compose up -d --no-build --no-deps --force-recreate web
cli cfg.php --name=theme --set=seavan
cli cfg.php --name=defaulthomepage --set=/my/index.php
cli cfg.php --name=guestloginbutton --set=0
cli purge_caches.php
cli maintenance.php --disable
docker compose up -d --no-build --no-deps --force-recreate cron processor
docker compose ps -a
echo "Backup saved locally: $backup"
echo 'Academy updated. Open the forwarded port 8080 and refresh the browser.'
