#!/usr/bin/env bash
set -e

# Attente de PostgreSQL (max 30s)
if [ -n "${DB_HOST:-}" ]; then
    echo "[entrypoint] Waiting for database ${DB_HOST}:${DB_PORT:-5432}..."
    for i in $(seq 1 30); do
        if pg_isready -h "${DB_HOST}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-smartwaste}" >/dev/null 2>&1; then
            echo "[entrypoint] Database is ready."
            break
        fi
        sleep 1
    done
fi

# Le service "app" (php-fpm) exécute les tâches de setup une seule fois.
# Les autres services (queue, scheduler) ne les rejouent pas.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "[entrypoint] Running migrations & caches..."
    php artisan config:clear || true

    # Générer APP_KEY si absent
    if ! grep -q "^APP_KEY=base64:" /var/www/html/.env 2>/dev/null; then
        php artisan key:generate --force || true
    fi

    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache || true
    php artisan storage:link || true
fi

exec "$@"
