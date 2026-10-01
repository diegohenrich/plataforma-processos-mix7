FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts

FROM php:8.3-apache-bookworm AS php-extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpq-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring opcache pcntl pdo_pgsql pgsql zip \
    && rm -rf /var/lib/apt/lists/*

FROM postgres:17-bookworm AS postgres-client

FROM php:8.3-apache-bookworm AS runtime
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN apt-get update \
    && apt-get install -y --no-install-recommends libgssapi-krb5-2 libldap-2.5-0 liblz4-1 libonig5 libpq5 libssl3 libzip4 libzstd1 unzip zlib1g \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod headers rewrite \
    && sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && printf '\nServerTokens Prod\nServerSignature Off\n' >> /etc/apache2/conf-available/security.conf \
    && a2enconf security
COPY --from=php-extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-extensions /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=postgres-client /usr/lib/postgresql/17/bin/pg_dump /usr/local/bin/pg_dump
COPY --from=postgres-client /usr/lib/x86_64-linux-gnu/libpq.so.5* /usr/local/lib/
RUN ldconfig \
    && pg_dump --version | grep -E '^pg_dump \(PostgreSQL\) 17\.'
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/mpm-prefork.conf /etc/apache2/mods-available/mpm-prefork.conf
COPY docker/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/gestao-entrypoint
WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/ /var/www/html/
COPY --from=assets --chown=www-data:www-data /app/public/build/ /var/www/html/public/build/
RUN mkdir -p storage/app/private storage/app/backups storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u=rwX,g=rX,o= storage bootstrap/cache
EXPOSE 8080
ENTRYPOINT ["gestao-entrypoint"]
CMD ["apache2-foreground"]
