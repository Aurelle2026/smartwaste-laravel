# Déploiement SmartWaste — VPS partagé (Docker)

Cible : VPS **37.187.195.28** (Ubuntu 24.04) déjà en prod avec plusieurs autres apps.
Isolation totale via Docker Compose. **Le build se fait sur le VPS**, on n'envoie rien depuis le poste local.

---

## Architecture

```
                  ┌────────────────────────────────────┐
                  │  VPS 37.187.195.28  (ubuntu)       │
                  │                                    │
  Client ──HTTP──►│  :8090 ── nginx (container)        │
                  │              │                     │
                  │              └──► app (php-fpm 8.3)│
                  │                     │              │
                  │              ┌──────┴─────┐        │
                  │              ▼            ▼        │
                  │         db (pg 16)   queue+scheduler│
                  │            :5435                   │
                  └────────────────────────────────────┘
```

Aucun conflit avec les autres apps qui tournent déjà (ports 80/443, 5433/5434, 8080/8081/8082…).

---

## Ports utilisés par SmartWaste

| Service | Port host | Note |
|---|---|---|
| Nginx (app web) | **8090** | modifiable via `HTTP_PORT` dans `.env` |
| PostgreSQL | **5435** | modifiable via `DB_PORT`, exposé pour admin local |

---

## Premier déploiement

### 1) Connexion SSH au VPS

```bash
ssh ubuntu@37.187.195.28
# mot de passe : Jaimeledebat01#
```

### 2) Lancer le déploiement

Le script fait tout : clone du repo, création du `.env` (avec un mot de passe DB généré), build Docker, démarrage.

```bash
curl -fsSL https://raw.githubusercontent.com/Aurelle2026/smartwaste-laravel/main/deploy/deploy.sh -o /tmp/deploy.sh
bash /tmp/deploy.sh install
```

Alternative si le script n'est pas encore poussé sur GitHub :
```bash
git clone https://github.com/Aurelle2026/smartwaste-laravel.git ~/smartwaste-laravel
cd ~/smartwaste-laravel
bash deploy/deploy.sh install
```

⚠️ **Note bien le mot de passe DB affiché** à la fin de la première exécution.

### 3) Ajuster le .env (optionnel)

```bash
cd ~/smartwaste-laravel
nano .env
```

Choses à revoir :
- `APP_URL=http://37.187.195.28:8090` — mettre le vrai port si tu l'as changé
- `MAIL_*` — configurer un SMTP quand tu voudras envoyer des emails
- Ne touche pas à `DB_HOST=db` (nom du service Docker, résolu en interne)

Après édition :
```bash
docker compose restart
```

### 4) Vérification

```bash
docker compose ps
docker compose logs -f app | head -50
curl -I http://localhost:8090
```

Depuis ton poste :
```
http://37.187.195.28:8090
```

---

## Mises à jour (après un git push)

```bash
ssh ubuntu@37.187.195.28
cd ~/smartwaste-laravel
bash deploy/deploy.sh update
```

Ça fait : `git pull` + `docker compose build` + `docker compose up -d` + nettoyage des images obsolètes.

Les migrations Laravel tournent automatiquement au démarrage du conteneur `app` (via `RUN_MIGRATIONS=true` dans le compose).

---

## Commandes utiles

```bash
# Logs
docker compose logs -f app          # PHP-FPM + entrypoint
docker compose logs -f web          # Nginx
docker compose logs -f db           # PostgreSQL
docker compose logs -f queue        # Queue worker

# Shell dans le conteneur app
docker compose exec app sh
docker compose exec app php artisan tinker
docker compose exec app php artisan migrate:status

# Accès PostgreSQL
docker compose exec db psql -U smartwaste -d smartwaste

# Backup DB
docker compose exec db pg_dump -U smartwaste smartwaste > backup_$(date +%F).sql

# Restart un seul service
docker compose restart app
docker compose restart queue

# Tout arrêter (données conservées)
docker compose down

# Tout supprimer, même les volumes (⚠️ perte de données)
docker compose down -v
```

---

## Rollback

```bash
cd ~/smartwaste-laravel
git log --oneline -10                    # trouver le commit précédent
git reset --hard <commit-hash>
docker compose build
docker compose up -d
```

---

## Ce qui persiste dans des volumes Docker

- `smartwaste_db_data` → toutes les données PostgreSQL
- `smartwaste_storage` → `storage/` de Laravel (logs, uploads, sessions files)
- `smartwaste_app_public` → dossier `public/` (assets Vite compilés)

Ces volumes survivent aux `docker compose down` (mais pas à `down -v`).

---

## Ajouter un domaine + HTTPS plus tard

Le VPS a déjà un reverse-proxy Docker qui écoute sur 80/443. Pour ajouter un domaine SmartWaste :

1. Faire pointer le DNS `smartwaste.tondomaine.com` → `37.187.195.28`
2. Ajouter une entrée dans le reverse-proxy existant (Nginx Proxy Manager, Traefik, ou nginx host) qui proxifie vers `127.0.0.1:8090`
3. Générer le certificat Let's Encrypt via ce proxy
4. Passer `APP_URL=https://smartwaste.tondomaine.com` + `SESSION_SECURE_COOKIE=true` dans `.env` puis `docker compose restart`

---

## Sécurité

- [ ] Le port 5435 (PostgreSQL) est exposé pour admin externe : à fermer via UFW quand plus utile (`sudo ufw deny 5435`)
- [ ] Passer en clef SSH plutôt que password
- [ ] `APP_DEBUG=false` déjà par défaut dans le template
- [ ] Backup DB régulier via cron (`pg_dump` dans un script journalier)
