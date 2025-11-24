# NPanel Deployment Guide

## Voraussetzungen

### Server-Anforderungen
- Ubuntu 22.04 LTS (empfohlen) oder ähnlich
- Nginx
- MySQL/MariaDB 10.4+
- PHP 8.2+ mit Extensions:
  - php-fpm, php-mysql, php-mbstring, php-xml, php-curl
  - php-zip, php-bcmath, php-json, php-tokenizer
- Node.js 18+ & NPM
- Composer 2.x
- Git
- Certbot mit dns-hetzner Plugin (für Wildcard SSL)

### Hetzner DNS API
- API Token aus Hetzner DNS Console
- Domains müssen in Hetzner DNS konfiguriert sein

---

## Erste Installation (Production)

### 1. Server vorbereiten

```bash
# System aktualisieren
sudo apt update && sudo apt upgrade -y

# Software-properties-common installieren (für PPA)
sudo apt install -y software-properties-common git

# PHP Repository hinzufügen
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

# Benötigte Pakete installieren
sudo apt install -y nginx mariadb-server php8.2-fpm php8.2-mysql \
    php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath \
    php8.2-tokenizer composer nodejs npm certbot python3-certbot-dns-hetzner

# MariaDB sichern
sudo mysql_secure_installation
```

### 2. Datenbank erstellen

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE npanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'npanel'@'localhost' IDENTIFIED BY 'SICHERES_PASSWORT';
GRANT ALL PRIVILEGES ON npanel.* TO 'npanel'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Projekt klonen

```bash
cd /var/www
sudo git clone https://github.com/marion909/npanel.git
sudo chown -R $USER:www-data npanel
cd npanel
```

### 4. Umgebung konfigurieren

```bash
# .env aus Vorlage erstellen
cp .env.example .env
nano .env
```

Wichtige Einstellungen in `.env`:

```env
APP_NAME=NPanel
APP_ENV=production
APP_DEBUG=false
APP_URL=https://panel.deine-domain.de

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=npanel
DB_USERNAME=npanel
DB_PASSWORD=DEIN_DB_PASSWORT

HETZNER_DNS_API_TOKEN=DEIN_HETZNER_TOKEN
WILDCARD_SSL_EMAIL=admin@deine-domain.de

APP_SERVER_IPV4=DEINE_SERVER_IP

NGINX_SITES_AVAILABLE=/etc/nginx/sites-available
NGINX_SITES_ENABLED=/etc/nginx/sites-enabled
DOCUMENT_ROOT_BASE=/var/www
PHP_FPM_SOCKETS_PATH=/run/php
```

### 5. Deployment ausführen

```bash
chmod +x deploy.sh
./deploy.sh --first-time
```

Das Skript führt automatisch aus:
- Composer & NPM Installation
- Asset-Build
- Migrations
- System-Setup
- Hetzner Zone Scan
- Caching & Optimierung

### 6. Nginx konfigurieren

```bash
sudo nano /etc/nginx/sites-available/npanel
```

Beispiel-Konfiguration:

```nginx
server {
    listen 80;
    server_name panel.deine-domain.de;
    root /var/www/npanel/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
# Nginx-Config aktivieren
sudo ln -s /etc/nginx/sites-available/npanel /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 7. SSL-Zertifikat einrichten

```bash
sudo certbot --nginx -d panel.deine-domain.de
```

### 8. Cron-Jobs einrichten

```bash
crontab -e
```

Hinzufügen:

```cron
* * * * * cd /var/www/npanel && php artisan schedule:run >> /dev/null 2>&1
```

### 9. Supervisor für Queue Worker (optional)

```bash
sudo apt install supervisor
sudo nano /etc/supervisor/conf.d/npanel-worker.conf
```

```ini
[program:npanel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/npanel/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/npanel/storage/logs/worker.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start npanel-worker:*
```

---

## Updates (Production)

Für Updates einfach Deployment-Skript erneut ausführen:

```bash
cd /var/www/npanel
./deploy.sh
```

Das Skript:
- Aktiviert Wartungsmodus
- Pulled neuesten Code
- Aktualisiert Dependencies
- Führt Migrations aus
- Optimiert Caches
- Startet Services neu
- Deaktiviert Wartungsmodus

---

## Entwicklung (Windows/Local)

### Setup

```powershell
# Repository klonen
git clone https://github.com/marion909/npanel.git
cd npanel

# Dependencies installieren
composer install
npm install --legacy-peer-deps

# .env konfigurieren
copy .env.example .env
# APP_KEY generieren
php artisan key:generate

# SQLite Datenbank (oder MySQL für lokalen XAMPP)
# DB_CONNECTION=sqlite bereits in .env

# Migrations ausführen
php artisan migrate

# Assets bauen
npm run build

# Dev-Server starten
php artisan serve
# In separatem Terminal:
npm run dev
```

### Updates (Development)

**Windows:**
```powershell
.\update-dev.bat
```

**Linux/Mac:**
```bash
chmod +x update-dev.sh
./update-dev.sh
```

---

## Wichtige Commands

```bash
# System-Setup
php artisan system:install

# Hetzner DNS
php artisan hetzner:zone-scan     # Zones für Domains finden
php artisan hetzner:sync          # DNS Records synchronisieren

# SSL Zertifikate
php artisan ssl:issue example.com # Wildcard SSL für Domain
php artisan ssl:renew             # Alle SSL erneuern
php artisan ssl:renew --domain=example.com  # Einzeln erneuern

# Logs ansehen
php artisan pail                  # Live-Logs
tail -f storage/logs/laravel.log  # Laravel Log
```

---

## Troubleshooting

### Permissions
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Cache löschen
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### PHP-FPM Neustart
```bash
sudo systemctl restart php8.2-fpm
```

### Nginx Neustart
```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## Sicherheit

- `.env` niemals committen
- `APP_DEBUG=false` in Production
- Starke Datenbank-Passwörter
- Regelmäßige Backups (DB + `.env`)
- Firewall konfigurieren (UFW)
- Fail2ban einrichten
- SSL erzwingen

---

## Backup

```bash
# Datenbank-Backup
mysqldump -u npanel -p npanel > backup-$(date +%Y%m%d).sql

# .env sichern
cp .env .env.backup

# Vollständiges Backup
tar -czf npanel-backup-$(date +%Y%m%d).tar.gz \
    /var/www/npanel/.env \
    /var/www/npanel/storage
```

---

## Support

Bei Fragen oder Problemen:
- GitHub Issues: https://github.com/marion909/npanel/issues
- Logs prüfen: `storage/logs/laravel.log`
- Laravel Docs: https://laravel.com/docs
