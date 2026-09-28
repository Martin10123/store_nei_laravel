#!/bin/sh
set -e
cd /var/www/html

if ! grep -q '"name": "predis/predis"' composer.lock 2>/dev/null; then
  composer update predis/predis --with-dependencies --no-interaction --prefer-dist
else
  composer install --no-interaction --prefer-dist
fi

php artisan migrate --force
php artisan db:seed --force

exec php artisan serve --host=0.0.0.0 --port=8000
