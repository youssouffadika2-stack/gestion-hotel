#!/bin/sh
set -e
cd /var/www/html
php artisan package:discover --ansi || true
# attend la base puis applique les migrations
for i in $(seq 1 30); do php artisan migrate --force && break; echo "base indisponible, nouvel essai..."; sleep 3; done
php artisan storage:link 2>/dev/null || true
php artisan config:cache && php artisan route:cache && php artisan view:cache || true
chown -R www-data:www-data storage bootstrap/cache
exec "$@"
