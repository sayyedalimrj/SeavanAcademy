FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends curl unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libxml2-dev libonig-dev libldap2-dev libcurl4-openssl-dev libwebp-dev && \
 docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
 docker-php-ext-install -j2 gd intl zip mysqli pdo_mysql soap exif opcache mbstring curl dom simplexml xml xmlreader xmlwriter && \
 a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
# Source is imported in GitHub or downloaded from the official fixed release.
COPY . /tmp/seavan-build
RUN set -eux; \
 if [ -f /tmp/seavan-build/moodle/version.php ]; then cp -a /tmp/seavan-build/moodle/. /var/www/html/; \
 else curl --fail --location --retry 3 https://download.moodle.org/download.php/direct/stable405/moodle-4.5.16.tgz -o /tmp/moodle.tgz; \
 echo '44b77196f6a7a73ad15a329d5669a7fbd9624b6b58b132e4e69ffebc7dcc2031  /tmp/moodle.tgz' | sha256sum -c -; \
 tar -xzf /tmp/moodle.tgz --strip-components=1 -C /var/www/html; rm /tmp/moodle.tgz; fi; \
 cp /tmp/seavan-build/docker/config.php /var/www/html/config.php; \
 mkdir -p /opt/seavan/lang /var/moodledata; \
 curl --fail --location --retry 3 https://packaging.moodle.org/langpack/4.5/fa.zip -o /tmp/fa.zip; \
 unzip -q /tmp/fa.zip -d /opt/seavan/lang; test -f /opt/seavan/lang/fa/langconfig.php; \
 cp /tmp/seavan-build/docker/init.sh /usr/local/bin/seavan-init; \
 chmod +x /usr/local/bin/seavan-init; \
 cp /tmp/seavan-build/docker/php.ini /usr/local/etc/php/conf.d/seavan.ini; \
 chown -R root:root /var/www/html; chown -R www-data:www-data /var/moodledata; \
 rm -rf /tmp/fa.zip /tmp/seavan-build
