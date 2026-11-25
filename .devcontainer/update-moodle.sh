#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# Updates the existing development containers. No volumes or accounts are deleted.
[[ -n "${CODESPACE_NAME:-}" ]] || { echo 'This fast update is for Codespaces only.'; exit 1; }
docker compose exec -T web true
bash docker/fetch-fonts.sh moodle/theme/seavan/fonts
cli() { docker compose exec -T -u www-data web php "/var/www/html/admin/cli/$1" "${@:2}"; }
# Enable maintenance before copying plugin code.
cli maintenance.php --enable
docker compose stop cron
for service in web cron; do
  docker compose cp moodle/theme/seavan/. "$service:/var/www/html/theme/seavan/"
  docker compose cp moodle/local/seavan/. "$service:/var/www/html/local/seavan/"
  docker compose cp docker/config.php "$service:/var/www/html/config.php"
done
docker compose exec -T web sh -c 'find /var/www/html/theme/seavan /var/www/html/local/seavan -name "*.php" -exec php -l {} \;'
cli upgrade.php --non-interactive
cli cfg.php --name=theme --set=seavan
cli cfg.php --name=guestloginbutton --set=0
cli cfg.php --name=defaulthomepage --set=/local/seavan/index.php
cli cfg.php --component=local_seavan --name=homeconfigured --set=1
docker compose exec -T -u www-data web php /var/www/html/local/seavan/cli/setup_categories.php
cli purge_caches.php
cli maintenance.php --disable
docker compose restart web
docker compose start cron
echo 'Updated live Moodle. Use Ctrl+F5 to refresh your browser.'
echo 'If the update stops with an error, inspect the output before retrying. Maintenance mode remains enabled until a successful update.'
