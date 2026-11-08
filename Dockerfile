# Web notepad image: PHP + Apache with the app baked in.
# Runs as www-data (uid 33) on port 8080; notes and attachments are stored in the /var/lib/notas volume.
# Build: docker build --pull --tag web-notepad .
# PHP_IMAGE is pinned to an exact patch release: >= 8.5.12 fixes php2shell (CVE-2026-109618, CVE-2026-109619).
# Bump it on new PHP security releases (https://www.php.net/) and rebuild with --pull for Debian updates.
ARG PHP_IMAGE=php:8.5.12-apache
FROM ${PHP_IMAGE}

LABEL org.opencontainers.image.title="web-notepad" \
      org.opencontainers.image.description="Single-file PHP web notepad with file attachments" \
      org.opencontainers.image.source="https://github.com/joseaguardia/web-notepad" \
      org.opencontainers.image.licenses="Apache-2.0"

# Apache on an unprivileged port, so it can run without root
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite headers \
    && sed --in-place 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed --in-place 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf \
    && mkdir --parents /var/lib/notas \
    && chown www-data:www-data /var/lib/notas

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-notepad.ini"
COPY docker/apache.conf /etc/apache2/conf-enabled/zz-notepad.conf
COPY index.php favicon.ico /var/www/html/

# App settings (see the top of index.php); raise PHP_POST_MAX_SIZE together with MAX_FILE_SIZE_MB
ENV NOTES_PATH=/var/lib/notas \
    MAX_FILE_SIZE_MB=5 \
    MAX_TOTAL_SIZE_MB=100 \
    MAX_NOTE_SIZE_MB=2 \
    MAX_NOTES=25 \
    NOTE_TTL_MINUTES=45 \
    PHP_POST_MAX_SIZE=8M

USER www-data
EXPOSE 8080
VOLUME /var/lib/notas

# "/" answers with a redirect to a random note, which is enough to know Apache and PHP are up
HEALTHCHECK --interval=1m --timeout=5s --retries=3 \
    CMD curl --fail --silent --output /dev/null http://localhost:8080/ || exit 1
