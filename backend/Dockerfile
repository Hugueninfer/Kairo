# syntax=docker/dockerfile:1

# Imagem de produção da API. A mesma imagem roda a API (FrankenPHP), a fila,
# o agendador e o WebSocket (Reverb); muda só o comando de cada serviço no compose.

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# As extensões do PHP ficam na imagem final; aqui só baixamos os pacotes.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress --ignore-platform-req='ext-*'
COPY . .
RUN composer dump-autoload --no-dev --optimize

FROM dunglas/frankenphp:1-php8.3-alpine

RUN install-php-extensions pdo_pgsql redis intl gd bcmath zip pcntl opcache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

WORKDIR /app
COPY --from=vendor --chown=www-data:www-data /app /app
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-kairo.ini
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/kairo-entrypoint

RUN rm -f .env database/*.sqlite \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    # FrankenPHP guarda dados e configuração do Caddy nestas pastas.
    && mkdir -p /data/caddy /config/caddy \
    && chown -R www-data:www-data /data /config

USER www-data
EXPOSE 8000

ENTRYPOINT ["kairo-entrypoint"]
CMD ["frankenphp", "php-server", "--root", "public/", "--listen", ":8000"]
