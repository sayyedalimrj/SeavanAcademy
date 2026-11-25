#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
[[ -n "${CODESPACE_NAME:-}" && -f .env && -f compose.preview.yaml ]] || { echo 'Run from the existing SeavanAcademy-RC Codespace checkout.'; exit 1; }
grep -q -- '-8081\.' .env || { echo 'Not the port 8081 preview. Refusing to update.'; exit 1; }
mkdir -p courses courses2 courses3 backups
umask 077
d(){ docker compose -p seavan-rc -f compose.preview.yaml "$@"; }
# Build candidate without interrupting the existing site.
sed -e 's/:8080:80/:8081:80/g' -e 's/seavan-academy:4.5.16/seavan-academy-rc:4.5.16/g' compose.yaml > compose.preview.yaml
d config --quiet
d build web
d exec -T web true
d exec -T -u www-data web php /var/www/html/admin/cli/maintenance.php --enable
d --profile whisper stop cron processor whisper
backup="backups/preview-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir "$backup"
d exec -T db sh -c 'mariadb-dump --single-transaction --quick -uroot -p"$MARIADB_ROOT_PASSWORD" moodle' | gzip > "$backup/database.sql.gz"
d exec -T web tar -C /var/moodledata --exclude=./cache --exclude=./localcache --exclude=./temp --exclude=./sessions -czf - . > "$backup/moodledata.tar.gz"
gzip -t "$backup/database.sql.gz"
tar -tzf "$backup/moodledata.tar.gz" >/dev/null
d stop web
d run --rm --no-deps init
d up -d --no-build --no-deps --force-recreate web
d exec -T -u www-data web php /var/www/html/admin/cli/cfg.php --name=theme --set=seavan
d exec -T -u www-data web php /var/www/html/admin/cli/cfg.php --name=custommenuitems --set=''
d exec -T -u www-data web php /var/www/html/admin/cli/cfg.php --name=defaulthomepage --set=/course/index.php
d exec -T -u www-data web php /var/www/html/admin/cli/purge_caches.php
d exec -T -u www-data web php /var/www/html/admin/cli/maintenance.php --disable
d up -d --no-build --no-deps --force-recreate cron processor
d ps -a
echo "Backup: $backup"
echo "Preview: https://${CODESPACE_NAME}-8081.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
