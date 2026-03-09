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
    
    # Filament cache
    php artisan filament:cache-components || true

    echo "Running migrations..."
    # Roda as migrações forçadamente em produção (requer confirmação automática --force)
    php artisan migrate --force
fi

# Executa o comando passado no CMD (seja o supervisor, horizon ou scheduler)
exec "$@"
