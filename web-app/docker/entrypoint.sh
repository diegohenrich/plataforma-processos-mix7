#!/bin/sh
set -eu

cd /var/www/html
php artisan package:discover --ansi
php artisan config:cache

exec "$@"
