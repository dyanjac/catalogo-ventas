# Docker build notes - Greenter

`greenter/greenter` ya está declarado en `composer.json`, por lo que en build Docker se instalará automáticamente al ejecutar `composer install`.

## Ejemplo mínimo en Dockerfile (PHP-FPM)

```dockerfile
FROM php:8.3-fpm

WORKDIR /var/www/html

# Dependencias del sistema para composer/openssl/zip
RUN apt-get update && apt-get install -y \
    git unzip zip libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

COPY . .
```

## Variables recomendadas (.env)

```env
GREENTER_DEFAULT_CERT_PATH=storage/app/certificados/greenter-test-bundle.pem
GREENTER_DEFAULT_CERT_PASSWORD=MaestroTest2026!
```
