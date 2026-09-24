#!/usr/bin/env bash
# Setup initial d'un VPS Ubuntu 24.04 pour Laravel + PostgreSQL + Nginx + PHP-FPM 8.3
# À lancer en root : sudo bash setup-vps.sh
set -euo pipefail

APP_USER="deploy"
APP_DIR="/var/www/smartwaste"
DB_NAME="smartwaste"
DB_USER="smartwaste"
DB_PASSWORD="${DB_PASSWORD:-$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)}"

echo ">>> [1/8] Mise à jour du système"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y

echo ">>> [2/8] Paquets de base"
apt-get install -y software-properties-common curl git unzip ca-certificates gnupg lsb-release ufw

echo ">>> [3/8] Dépôt PHP 8.3 (ondrej/php)"
add-apt-repository -y ppa:ondrej/php
apt-get update -y

echo ">>> [4/8] Installation PHP 8.3 + extensions Laravel"
apt-get install -y \
    php8.3 php8.3-fpm php8.3-cli \
    php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd \
    php8.3-tokenizer php8.3-fileinfo php8.3-opcache

echo ">>> [5/8] Composer"
if ! command -v composer >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi

echo ">>> [6/8] Node.js 20 LTS + npm"
if ! command -v node >/dev/null 2>&1; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

echo ">>> [7/8] PostgreSQL"
apt-get install -y postgresql postgresql-contrib
systemctl enable --now postgresql

# Création DB & user (idempotent)
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASSWORD}';"
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};"

echo ">>> [8/8] Nginx"
apt-get install -y nginx
systemctl enable --now nginx

# Utilisateur de déploiement
if ! id "${APP_USER}" >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" "${APP_USER}"
    usermod -aG www-data "${APP_USER}"
fi

# Dossiers app
mkdir -p "${APP_DIR}/releases" "${APP_DIR}/shared/storage" "${APP_DIR}/shared"
chown -R "${APP_USER}:www-data" "${APP_DIR}"
chmod -R 775 "${APP_DIR}"

# Firewall
ufw allow OpenSSH || true
ufw allow 'Nginx Full' || true
yes | ufw enable || true

echo ""
echo "=============================================="
echo "  VPS prêt !"
echo "=============================================="
echo "  DB name     : ${DB_NAME}"
echo "  DB user     : ${DB_USER}"
echo "  DB password : ${DB_PASSWORD}"
echo "  App user    : ${APP_USER}"
echo "  App dir     : ${APP_DIR}"
echo ""
echo "  Prochaines étapes :"
echo "  1. Note bien le mot de passe DB ci-dessus."
echo "  2. Bascule sur l'user deploy : su - ${APP_USER}"
echo "  3. Lance : bash deploy.sh"
echo "=============================================="
