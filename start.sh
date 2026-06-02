#!/bin/bash
set -e

echo "==> Debug: Checking DB environment variables..."
echo "DB_CONNECTION=${DB_CONNECTION}"
echo "DB_HOST=${DB_HOST}"
echo "DB_PORT=${DB_PORT}"
echo "DB_DATABASE=${DB_DATABASE}"
echo "DB_USERNAME=${DB_USERNAME}"
echo "DB_PASSWORD=$(echo ${DB_PASSWORD} | head -c 3)***"
echo "DB_URL=$(echo ${DB_URL} | sed 's/:[^@]*@/:***@/')"
echo "DATABASE_URL=$(echo ${DATABASE_URL} | sed 's/:[^@]*@/:***@/')"
echo "MYSQL_URL=$(echo ${MYSQL_URL} | sed 's/:[^@]*@/:***@/')"
echo "MYSQL_PUBLIC_URL=$(echo ${MYSQL_PUBLIC_URL} | sed 's/:[^@]*@/:***@/')"
echo ""

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
