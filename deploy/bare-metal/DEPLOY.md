# Déploiement SmartWaste sur VPS

Stack cible : **Ubuntu 24.04 LTS + Nginx + PHP-FPM 8.3 + PostgreSQL 16 + Node 20**.
Stratégie : releases atomiques dans `/var/www/smartwaste/releases/<timestamp>`, symlink `current`.

---

## 0. Pré-requis

- Un VPS Ubuntu 24.04 fraîchement provisionné.
- Un accès `root` (ou `sudo`) en SSH.
- Ton IP publique du VPS notée (ex. `203.0.113.42`).

---

## 1. Copier les fichiers de déploiement sur le VPS

Depuis ta machine locale :

```bash
# Envoie le dossier deploy/ sur le VPS
scp -r deploy/ root@VPS_IP:/root/
```

---

## 2. Setup initial du VPS

Sur le VPS, en `root` :

```bash
cd /root/deploy
chmod +x setup-vps.sh
sudo bash setup-vps.sh
```

Le script installe : PHP 8.3 + extensions, Composer, Node 20, PostgreSQL, Nginx, UFW, et crée l'utilisateur `deploy` + la DB `smartwaste`.

**⚠️ Note bien le mot de passe DB affiché à la fin.**

---

## 3. Configurer Nginx

```bash
sudo cp /root/deploy/nginx.conf /etc/nginx/sites-available/smartwaste
sudo ln -sf /etc/nginx/sites-available/smartwaste /etc/nginx/sites-enabled/smartwaste
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

---

## 4. Préparer le `.env` de prod

Toujours en root (ou avec sudo) :

```bash
sudo -u deploy cp /root/deploy/.env.production.example /var/www/smartwaste/shared/.env
sudo -u deploy nano /var/www/smartwaste/shared/.env
```

À modifier impérativement :
- `APP_URL=http://VPS_IP` (ton IP publique)
- `DB_PASSWORD=` → colle le mot de passe généré à l'étape 2
- `APP_KEY=` reste vide, il sera généré à l'étape suivante

---

## 5. Premier déploiement

```bash
sudo -u deploy cp /root/deploy/deploy.sh /home/deploy/deploy.sh
sudo chmod +x /home/deploy/deploy.sh

# Autoriser deploy à recharger PHP-FPM et le service queue via sudo sans mot de passe
sudo tee /etc/sudoers.d/deploy-smartwaste > /dev/null <<'EOF'
deploy ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.3-fpm
deploy ALL=(ALL) NOPASSWD: /bin/systemctl restart smartwaste-queue.service
deploy ALL=(ALL) NOPASSWD: /bin/chown -R deploy\:www-data /var/www/smartwaste/*
deploy ALL=(ALL) NOPASSWD: /bin/chmod -R 775 /var/www/smartwaste/*
EOF

# Passe en user deploy et déploie
sudo su - deploy
bash ~/deploy.sh
```

Après le premier `deploy.sh`, génère la clef d'app :

```bash
cd /var/www/smartwaste/current
php artisan key:generate --force
php artisan config:cache
```

---

## 6. Activer les services queue + scheduler

En root :

```bash
sudo cp /root/deploy/smartwaste-queue.service /etc/systemd/system/
sudo cp /root/deploy/smartwaste-scheduler.service /etc/systemd/system/
sudo cp /root/deploy/smartwaste-scheduler.timer /etc/systemd/system/

sudo systemctl daemon-reload
sudo systemctl enable --now smartwaste-queue.service
sudo systemctl enable --now smartwaste-scheduler.timer
```

Vérif :

```bash
sudo systemctl status smartwaste-queue.service
sudo systemctl list-timers | grep smartwaste
```

---

## 7. Test final

Ouvre ton navigateur sur `http://VPS_IP`. L'app doit répondre.

En cas de 500 :

```bash
sudo tail -f /var/www/smartwaste/current/storage/logs/laravel.log
sudo tail -f /var/log/nginx/error.log
```

---

## 8. Déploiements suivants

À chaque push sur `main` :

```bash
ssh deploy@VPS_IP 'bash ~/deploy.sh'
```

Rollback (si besoin) : pointer manuellement `current` vers une release précédente.

```bash
ls /var/www/smartwaste/releases/     # liste les timestamps
sudo -u deploy ln -sfn /var/www/smartwaste/releases/<TS_PRECEDENT> /var/www/smartwaste/current
sudo systemctl reload php8.3-fpm
```

---

## 9. Sécurité (à faire dès que possible)

- [ ] Désactiver le login SSH root (`PermitRootLogin no` dans `/etc/ssh/sshd_config`)
- [ ] Authentification SSH par clef uniquement (`PasswordAuthentication no`)
- [ ] Installer `fail2ban` : `sudo apt install fail2ban -y`
- [ ] Quand tu auras un domaine : `sudo apt install certbot python3-certbot-nginx` puis `sudo certbot --nginx -d ton-domaine.com`
- [ ] Passer `APP_DEBUG=false` dans le `.env` (déjà par défaut ici)
- [ ] Sauvegardes PostgreSQL régulières (`pg_dump` en cron)
