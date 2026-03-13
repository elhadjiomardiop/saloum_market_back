#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${DB_CONNECTION:-}" = "sqlite" ]; then
  DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
  DB_DIR="$(dirname "$DB_FILE")"
  mkdir -p "$DB_DIR"
  if [ ! -e "$DB_FILE" ]; then
    touch "$DB_FILE"
  fi
  chown -R www-data:www-data "$DB_DIR"
fi

if [ ! -e public/storage ]; then
  php artisan storage:link
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
fi

exec apache2-foreground
