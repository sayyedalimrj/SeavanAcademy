#!/bin/sh
set -eu
mkdir -p /var/moodledata/lang
cp -a /opt/seavan/lang/fa /var/moodledata/lang/
chown -R www-data:www-data /var/moodledata/lang
chown www-data:www-data /var/moodledata
# Existing installations are not reinstalled. A failed initial install must be investigated.
if php -r '$d = new mysqli("db","moodle",getenv("DB_PASSWORD"),"moodle"); $r=$d->query("SHOW TABLES LIKE \'mdl_config\'"); exit($r->num_rows ? 0 : 1);'; then
  echo 'Existing database detected; checking for pending upgrade.'
  su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/upgrade.php --non-interactive'
else
  su -s /bin/sh www-data -c 'php /var/www/html/admin/cli/install_database.php --agree-license --lang=fa --fullname="آکادمی سیوان" --shortname="آکادمی سیوان" --adminuser=admin --adminpass="$ADMIN_PASSWORD" --adminemail="$ADMIN_EMAIL"'
fi
