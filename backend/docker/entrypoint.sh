#!/bin/sh
# Entrypoint backend: siapkan .env, cache konfigurasi, opsional migrasi.
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force || true
fi

php artisan package:discover --ansi || true
php artisan config:cache || true
php artisan route:cache || true

# Set RUN_MIGRATIONS=true pada service yang boleh menjalankan migrasi.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
