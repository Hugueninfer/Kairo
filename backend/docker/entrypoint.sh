#!/bin/sh
set -e

# Só o serviço da API roda as migrations, para não correrem em paralelo com a fila e o agendador.
if [ "$1" = "frankenphp" ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

# Cache de config, rotas, views e eventos, com as variáveis de ambiente do container.
php artisan optimize

exec "$@"
