#!/bin/sh
set -eu
mkdir -p /var/moodledata/lang
cp -a /opt/seavan/lang/fa /var/moodledata/lang/
chown -R www-data:www-data /var/moodledata/lang
chown www-data:www-data /var/moodledata
# Database connection failures must not be mistaken for an empty database.
state=$(php -r '$d = new mysqli("db","moodle",getenv("DB_PASSWORD"),"moodle"); $r=$d->query("SHOW TABLES"); $n=$r->num_rows; while($row=$r->fetch_row()){if($row[0]==="mdl_config"){echo "installed";exit;}} echo $n ? "partial" : "empty";')
case "$state" in
  installed)
    su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/upgrade.php --non-interactive'
    ;;
  empty)
    su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/install_database.php --agree-license --lang=fa --fullname="آکادمی سیوان" --shortname="آکادمی سیوان" --adminuser=admin --adminpass="$ADMIN_PASSWORD" --adminemail="$ADMIN_EMAIL"'
    su -s /bin/sh www-data -c 'php /var/www/html/local/seavan/cli/setup_categories.php'
    ;;
  *) echo "Non-empty incomplete database; inspect before retrying." >&2; exit 1 ;;
esac

# Apply the Academy theme and menu once; subsequent restarts preserve admin settings.
if ! su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/cfg.php --component=local_seavan --name=brandconfigured --no-eol' >/dev/null 2>&1; then
  su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/cfg.php --name=theme --set=seavan'
  su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/cfg.php --name=custommenuitems --set="آکادمی سیوان|$SITE_URL/local/seavan/index.php"'
  su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/cfg.php --component=local_seavan --name=brandconfigured --set=1'
  su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/purge_caches.php'
fi

# Make the native Academy page the first destination; do not override later admin changes.
if ! su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/cfg.php --component=local_seavan --name=homeconfigured --no-eol' >/dev/null 2>&1; then
  su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/cfg.php --name=guestloginbutton --set=0'
  su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/cfg.php --name=defaulthomepage --set=/my/index.php'
  su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/cfg.php --component=local_seavan --name=homeconfigured --set=1'
  su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/purge_caches.php'
fi
