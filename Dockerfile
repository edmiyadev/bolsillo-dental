# --- ETAPA BASE ---
FROM php:8.4-fpm AS base
WORKDIR /var/www

# Copiar Composer desde la imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar instalador de extensiones PHP optimizado (binarios precompilados)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Instalar herramientas del sistema y extensiones PHP requeridas por Laravel y Filament
RUN apt-get update && apt-get install -y --no-install-recommends \
    zip unzip git \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_pgsql bcmath intl zip gd opcache pcntl

# --- ETAPA DE DESARROLLO ---
FROM base AS development
CMD ["php-fpm"]

# --- ETAPA FRONTEND (ASSETS) ---
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# --- ETAPA DE PRODUCCIÓN ---
FROM base AS production

# Configuración personalizada de PHP para producción
COPY docker/php/production.ini /usr/local/etc/php/conf.d/custom.ini

# 1. Cachear dependencias de producción de Composer
COPY composer*.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# 2. Copiar código de la aplicación y assets compilados
COPY . .
COPY --from=frontend /app/public/build /var/www/public/build

# 3. Generar autoloader optimizado, publicar assets de Filament y configurar permisos
RUN mkdir -p /var/www/storage/logs \
        /var/www/storage/framework/cache/data \
        /var/www/storage/framework/sessions \
        /var/www/storage/framework/views \
        /var/www/storage/app/public \
    && composer dump-autoload --optimize --no-dev \
    && php artisan filament:assets \
    && php artisan storage:link || true \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/public

USER www-data
CMD ["php-fpm"]

# --- ETAPA NGINX PRODUCCIÓN ---
FROM nginx:alpine AS nginx-production
COPY nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=production /var/www/public /var/www/public
