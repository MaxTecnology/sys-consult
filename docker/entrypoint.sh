#!/bin/sh
set -e

# Executa as otimizações e migrações se for o container web rodando o supervisord
if [ "$1" = "/usr/bin/supervisord" ]; then
    echo "Running configuration and cache optimizations..."
    
    # Laravel Optimizations
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    
    # Filament cache e assets
    php artisan filament:cache-components || true
    php artisan filament:optimize || true
    php artisan vendor:publish --tag=filament-assets --force || true

    echo "Running migrations..."
    # Roda as migrações forçadamente em produção (requer confirmação automática --force)
    php artisan migrate --force

    echo "Fixing storage permissions for uploaded files..."
    # Garante que o volume montado pelo Dokploy seja sempre "dono" do nginx/php-fpm
    chown -R www-data:www-data /var/www/html/storage
    chmod -R 775 /var/www/html/storage
fi

# Executa o comando passado no CMD (seja o supervisor, horizon ou scheduler)
exec "$@"
