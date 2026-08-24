#!/bin/bash
set -e

cd /var/www/html

: "${PORT:=10000}"

echo "==> Configurando nginx en el puerto ${PORT}"
sed "s/__PORT__/${PORT}/" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Las caches se generan aqui y no en el build porque dependen de las variables
# de entorno que Render solo inyecta en tiempo de ejecucion.
# route:cache queda fuera a proposito: routes/web.php usa un closure y Laravel
# no puede serializarlo.
echo "==> Cacheando configuracion y vistas"
php artisan config:cache
php artisan view:cache

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "==> Ejecutando migraciones"
  php artisan migrate --force
fi

# Solo para el primer despliegue: crea el admin y los catalogos.
# Despues pon RUN_SEEDERS=false para no re-sembrar en cada arranque.
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
  echo "==> Ejecutando seeders"
  php artisan db:seed --force
fi

chown -R www-data:www-data storage bootstrap/cache

echo "==> Levantando php-fpm y nginx"
php-fpm -D
exec nginx -g 'daemon off;'
