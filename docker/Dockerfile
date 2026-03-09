FROM php:8.4-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Instalar dependências de sistema
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    git \
    supervisor \
    nginx \
    icu-dev \
    oniguruma-dev \
    linux-headers

# Instalar extensões PHP necessárias para Laravel e Filament
RUN apk add --no-cache libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip \
    && apk add --no-cache pcre-dev $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del pcre-dev $PHPIZE_DEPS

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar configurações do Docker
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Tornar entrypoint executável
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copiar arquivos do projeto
COPY . .

# Fix git dubious ownership error in Dokploy
RUN git config --global --add safe.directory /var/www/html

# Instalar dependências do PHP (sem pacotes de dev) para produção
RUN composer install --optimize-autoloader --no-dev --no-interaction

# Instalar dependências Node e compilar Filament/Vite assets
RUN npm install \
    && npm run build \
    && rm -rf node_modules

# Otimizar Filament e publicar assets
RUN php artisan filament:optimize \
    && php artisan vendor:publish --tag=filament-assets --force

# Ajustar permissões para os diretórios do Nginx e do Laravel
# Alpine normalmente roda o nginx com dono nginx ou www-data, usamos www-data generalizado.
RUN mkdir -p /run/nginx \
    && chown -R www-data:www-data /var/www/html /run/nginx /var/lib/nginx /var/log/nginx \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expor porta 80 do Nginx
EXPOSE 80

# Definir Entrypoint e CMD
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Comando padrão (Iniciar Supervisor que iniciará Nginx e PHP-FPM via daemon)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
