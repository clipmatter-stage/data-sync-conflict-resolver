#!/bin/bash
set -e

echo "==> Clearing stale config cache..."
php artisan config:clear

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Caching config with runtime env vars..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Creating storage directories..."
mkdir -p storage/framework/{sessions,views,cache,testing} storage/logs bootstrap/cache
chmod -R a+rw storage bootstrap/cache

echo "==> Starting Laravel server on port ${PORT}..."
php artisan serve --host=0.0.0.0 --port=${PORT}
