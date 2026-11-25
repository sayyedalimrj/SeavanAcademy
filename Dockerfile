FROM php:8.3-apache-bookworm AS academy
RUN set -eux; \
 apt-get update; \
 apt-get install -y --no-install-recommends curl unzip ffmpeg libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libxml2-dev libonig-dev libwebp-dev; \
 if ! php -r 'exit(extension_loaded("gd") ? 0 : 1);'; then docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; fi; \
 for ext in gd intl zip mysqli pdo_mysql soap exif mbstring; do \
   if ! php -r 'exit(extension_loaded($argv[1]) ? 0 : 1);' "$ext"; then docker-php-ext-install -j2 "$ext"; fi; \
 done; \
 if ! php -r 'exit(extension_loaded("Zend OPcache") ? 0 : 1);'; then docker-php-ext-install -j2 opcache; fi; \
 php -r 'foreach (["curl","dom","SimpleXML","xml","xmlreader","xmlwriter","gd","intl","zip","mysqli","mbstring","sodium","Zend OPcache"] as $ext) { if (!extension_loaded($ext)) { fwrite(STDERR, "Missing PHP extension: $ext\n"); exit(1); }}'; \
 a2enmod rewrite headers; \
 rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
# Source is imported in GitHub or downloaded from the official fixed release.
COPY . /tmp/seavan-build
RUN set -eux; \
 if [ -f /tmp/seavan-build/moodle/version.php ]; then cp -a /tmp/seavan-build/moodle/. /var/www/html/; \
 else curl --fail --location --retry 3 https://download.moodle.org/download.php/direct/stable405/moodle-4.5.16.tgz -o /tmp/moodle.tgz; \
 echo '44b77196f6a7a73ad15a329d5669a7fbd9624b6b58b132e4e69ffebc7dcc2031  /tmp/moodle.tgz' | sha256sum -c -; \
 tar -xzf /tmp/moodle.tgz --strip-components=1 -C /var/www/html; rm /tmp/moodle.tgz; fi; \
 sh /tmp/seavan-build/docker/fetch-fonts.sh /var/www/html/theme/seavan/fonts; \
 cp /tmp/seavan-build/docker/config.php /var/www/html/config.php; \
 mkdir -p /opt/seavan/lang /var/moodledata; \
 curl --fail --location --retry 3 https://packaging.moodle.org/langpack/4.5/fa.zip -o /tmp/fa.zip; \
 unzip -q /tmp/fa.zip -d /opt/seavan/lang; test -f /opt/seavan/lang/fa/langconfig.php; \
 cp /tmp/seavan-build/docker/init.sh /usr/local/bin/seavan-init; \
 chmod +x /usr/local/bin/seavan-init; \
 cp /tmp/seavan-build/docker/php.ini /usr/local/etc/php/conf.d/seavan.ini; \
 chown -R root:root /var/www/html; chown -R www-data:www-data /var/moodledata; \
 rm -rf /tmp/fa.zip /tmp/seavan-build

# Optional CPU transcription worker, not included in the normal web image.
FROM academy AS whisper
RUN apt-get update && apt-get install -y --no-install-recommends python3 python3-venv && python3 -m venv /opt/seavan-whisper && /opt/seavan-whisper/bin/pip install --no-cache-dir faster-whisper==1.1.1 && rm -rf /var/lib/apt/lists/*
COPY docker/transcribe.py /opt/seavan-tools/transcribe.py
ENV HF_HOME=/var/moodledata/seavan-whisper-model
FROM academy AS runtime
