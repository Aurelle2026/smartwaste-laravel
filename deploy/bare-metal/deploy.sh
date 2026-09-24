#!/usr/bin/env bash
# Deploy / update de l'app SmartWaste (à lancer en user 'deploy' sur le VPS).
# Stratégie "releases" simple : chaque déploiement va dans releases/<timestamp>
# puis un symlink 'current' pointe vers la nouvelle release.
set -euo pipefail

REPO_URL="https://github.com/Aurelle2026/smartwaste-laravel.git"
BRANCH="${BRANCH:-main}"
APP_DIR="/var/www/smartwaste"
KEEP_RELEASES=5

TS="$(date +%Y%m%d%H%M%S)"
RELEASE_DIR="${APP_DIR}/releases/${TS}"
SHARED_DIR="${APP_DIR}/shared"
CURRENT_LINK="${APP_DIR}/current"

echo ">>> [1/8] Clone release ${TS}"
git clone --depth 1 --branch "${BRANCH}" "${REPO_URL}" "${RELEASE_DIR}"

echo ">>> [2/8] Lien du .env partagé"
if [ ! -f "${SHARED_DIR}/.env" ]; then
    echo "ERREUR : ${SHARED_DIR}/.env n'existe pas."
    echo "Crée-le d'abord (copie deploy/.env.production.example) puis relance."
    rm -rf "${RELEASE_DIR}"
    exit 1
fi
ln -sfn "${SHARED_DIR}/.env" "${RELEASE_DIR}/.env"

echo ">>> [3/8] Storage partagé"
rm -rf "${RELEASE_DIR}/storage"
ln -sfn "${SHARED_DIR}/storage" "${RELEASE_DIR}/storage"

echo ">>> [4/8] Dépendances PHP"
cd "${RELEASE_DIR}"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo ">>> [5/8] Build assets front"
npm ci
npm run build

echo ">>> [6/8] Migrations & caches Laravel"
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

echo ">>> [7/8] Bascule symlink 'current'"
ln -sfn "${RELEASE_DIR}" "${CURRENT_LINK}"

# Permissions storage/bootstrap-cache
sudo chown -R deploy:www-data "${RELEASE_DIR}/bootstrap/cache" "${SHARED_DIR}/storage"
sudo chmod -R 775 "${RELEASE_DIR}/bootstrap/cache" "${SHARED_DIR}/storage"

echo ">>> [8/8] Reload PHP-FPM & queue worker"
sudo systemctl reload php8.3-fpm
sudo systemctl restart smartwaste-queue.service 2>/dev/null || true

# Nettoyage anciennes releases
cd "${APP_DIR}/releases"
ls -1t | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

echo ""
echo "=============================================="
echo "  Déploiement terminé : ${TS}"
echo "  Release active : $(readlink ${CURRENT_LINK})"
echo "=============================================="
