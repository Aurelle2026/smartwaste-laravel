#!/usr/bin/env bash
# Deploy SmartWaste via Docker Compose (à lancer SUR LE VPS).
# Usage :
#   Premier déploiement : bash deploy.sh install
#   Mises à jour         : bash deploy.sh update
set -euo pipefail

APP_DIR="/home/ubuntu/smartwaste-laravel"
REPO_URL="https://github.com/Aurelle2026/smartwaste-laravel.git"
BRANCH="${BRANCH:-main}"

ACTION="${1:-update}"

install_first_time() {
    echo ">>> [1/5] Clone du repo dans ${APP_DIR}"
    if [ -d "${APP_DIR}/.git" ]; then
        echo "    Repo déjà présent, on saute le clone."
    else
        git clone --branch "${BRANCH}" "${REPO_URL}" "${APP_DIR}"
    fi
    cd "${APP_DIR}"

    if [ ! -f .env ]; then
        echo ">>> [2/5] Création du .env depuis le template"
        cp deploy/.env.docker.example .env
        DB_PWD="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"
        sed -i "s|DB_PASSWORD=CHANGE_ME_STRONG_PASSWORD|DB_PASSWORD=${DB_PWD}|" .env
        echo ""
        echo "    .env généré avec un mot de passe DB aléatoire."
        echo "    ⚠️  Mot de passe DB : ${DB_PWD}"
        echo "    ⚠️  Édite .env si besoin (APP_URL, mail, etc.) puis relance : bash deploy.sh update"
        echo ""
    else
        echo ">>> [2/5] .env déjà présent, on garde."
    fi

    echo ">>> [3/5] Build de l'image Docker (peut prendre 5-10 min la 1ère fois)"
    docker compose build

    echo ">>> [4/5] Démarrage des conteneurs"
    docker compose up -d

    echo ">>> [5/5] Attente que l'app soit prête..."
    sleep 8
    docker compose ps

    echo ""
    echo "=============================================="
    echo "  Installation terminée."
    echo "  Vérifie : http://37.187.195.28:$(grep ^HTTP_PORT .env | cut -d= -f2)"
    echo "  Logs    : docker compose logs -f app"
    echo "=============================================="
}

update_deployment() {
    cd "${APP_DIR}"

    echo ">>> [1/4] git pull"
    git fetch origin
    git reset --hard "origin/${BRANCH}"

    echo ">>> [2/4] Rebuild image"
    docker compose build

    echo ">>> [3/4] Recreate containers"
    docker compose up -d --remove-orphans

    echo ">>> [4/4] Cleanup images obsolètes"
    docker image prune -f

    echo ""
    echo "=============================================="
    echo "  Mise à jour terminée."
    docker compose ps
    echo "=============================================="
}

case "${ACTION}" in
    install) install_first_time ;;
    update)  update_deployment ;;
    *)
        echo "Usage: $0 [install|update]"
        exit 1
        ;;
esac
