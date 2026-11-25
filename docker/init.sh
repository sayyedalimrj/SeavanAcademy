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
    ;;
  *) echo "Non-empty incomplete database; inspect before retrying." >&2; exit 1 ;;
esac
