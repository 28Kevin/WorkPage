#!/bin/bash
set -e

cd /var/www/html

echo "==> Esperando a PostgreSQL en ${DB_HOST}:${DB_PORT}…"
until php -r "exit(@fsockopen(getenv('DB_HOST'), (int) getenv('DB_PORT')) ? 0 : 1);"; do
  sleep 2
done
echo "==> PostgreSQL disponible."

if [ ! -f .env ]; then
  echo "==> Creando .env a partir de .env.example"
  cp .env.example .env
fi

if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
  echo "==> Instalando dependencias de Composer…"
  composer install --no-interaction --prefer-dist --no-progress
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
  echo "==> Generando APP_KEY…"
  php artisan key:generate --force
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

echo "==> Ejecutando migraciones y seeders…"
php artisan migrate --force --seed

php artisan config:clear
php artisan route:clear

echo "==> Backend listo en http://localhost:${APP_PORT:-8000}"

exec "$@"
