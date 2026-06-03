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

# Map Railway's MySQL URL to DB_URL if individual DB vars are not set
if [ -z "$DB_HOST" ] && [ -n "$MYSQL_URL" ]; then
    echo "==> DB_HOST not set but MYSQL_URL found, exporting as DB_URL..."
    export DB_URL="$MYSQL_URL"
elif [ -z "$DB_HOST" ] && [ -n "$DATABASE_URL" ]; then
    echo "==> DB_HOST not set but DATABASE_URL found, exporting as DB_URL..."
    export DB_URL="$DATABASE_URL"
fi

echo "==> Clearing stale build-time config cache..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "==> Verifying database connection..."
php artisan db:monitor || echo "WARNING: Database connection check failed, continuing anyway..."

echo "==> Running migrations..."
php artisan migrate --force || echo "WARNING: Migrations failed, continuing anyway..."

echo "==> Caching config with runtime env vars..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Creating storage directories..."
mkdir -p storage/framework/{sessions,views,cache,testing} storage/logs bootstrap/cache
chmod -R a+rw storage bootstrap/cache

echo "==> Starting Laravel server on port ${PORT} with ${PHP_CLI_SERVER_WORKERS:-4} workers..."
php artisan serve --host=0.0.0.0 --port=${PORT} --no-reload
