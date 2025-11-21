# nPanel Installation & Update

## Fresh Installation

Für eine komplette Neuinstallation von nPanel auf einem frischen Ubuntu/Debian Server:

```bash
# Als root user
wget https://raw.githubusercontent.com/marion909/npanel/main/install.sh
chmod +x install.sh
./install.sh
```

### Was das Script installiert:

- ✅ Nginx Web Server
- ✅ MySQL/MariaDB Datenbank Server
- ✅ Redis für Queue und Cache
- ✅ PHP 7.4, 8.0, 8.1, 8.2, 8.3 mit PHP-FPM
- ✅ Composer (PHP Dependency Manager)
- ✅ Node.js 20.x & npm
- ✅ acme.sh für SSL-Zertifikate
- ✅ Supervisor für Queue Workers
- ✅ nPanel Laravel Application
- ✅ Nginx Konfiguration mit IP-Access und Catchall
- ✅ Automatische Migration der Datenbank
- ✅ Admin-User Erstellung

### Nach der Installation:

Das Script wird dich nach folgenden Informationen fragen:
- Email für SSL-Zertifikate (acme.sh)
- MySQL root Passwort (oder generiert eins)
- Admin Name, Email & Passwort

**Wichtig:** Speichere alle angezeigten Credentials! Sie werden in der `.env` Datei gespeichert.

### Zugriff auf das Panel:

Nach erfolgreicher Installation ist das Panel erreichbar unter:
```
http://DEINE_SERVER_IP
```

---

## Update bestehender Installation

Für Updates einer bereits installierten nPanel-Installation:

```bash
# Als root user
cd /var/www/npanel
wget https://raw.githubusercontent.com/marion909/npanel/main/update.sh
chmod +x update.sh
./update.sh
```

### Was das Update-Script macht:

1. **Backup erstellen:**
   - SQLite Datenbank → `/var/backups/npanel/database_TIMESTAMP.sqlite`
   - .env Datei → `/var/backups/npanel/.env_TIMESTAMP`
   - Storage Ordner → `/var/backups/npanel/storage_TIMESTAMP.tar.gz`

2. **Maintenance Mode aktivieren:**
   - Besucher sehen "Wartungsmodus" Seite
   - Verhindert Datenbankänderungen während Update

3. **Queue Workers stoppen:**
   - Laufende Background-Jobs werden beendet
   - Verhindert Fehler bei Code-Updates

4. **Code aktualisieren:**
   - `git pull` von GitHub Repository
   - Neue Features und Bugfixes werden geladen

5. **Dependencies aktualisieren:**
   - PHP: `composer install --no-dev --optimize-autoloader`
   - Node.js: `npm install`

6. **Frontend neu bauen:**
   - `npm run build` erstellt neue produktive Assets
   - Vue.js Komponenten werden kompiliert

7. **Datenbank migrieren:**
   - `php artisan migrate --force`
   - Neue Tabellen/Spalten werden angelegt

8. **Caches leeren & optimieren:**
   - Config, Route, View Cache neu generieren
   - Laravel wird für Production optimiert

9. **Permissions aktualisieren:**
   - www-data Benutzer bekommt richtige Rechte

10. **Services neustarten:**
    - Nginx reload
    - PHP-FPM restart
    - Queue Workers restart

### Bei fehlgeschlagenem Update:

Das Script gibt dir Rollback-Anweisungen:

```bash
# Datenbank wiederherstellen
cp /var/backups/npanel/database_TIMESTAMP.sqlite /var/www/npanel/database/database.sqlite

# .env wiederherstellen
cp /var/backups/npanel/.env_TIMESTAMP /var/www/npanel/.env

# Storage wiederherstellen
tar -xzf /var/backups/npanel/storage_TIMESTAMP.tar.gz -C /var/www/npanel

# Code zurücksetzen
cd /var/www/npanel && git reset --hard HEAD~1

# Services neustarten
systemctl reload nginx
supervisorctl restart npanel-worker:*
php artisan up
```

---

## Manuelle Update-Schritte

Falls du das Update manuell durchführen möchtest:

```bash
# 1. Backup erstellen
mkdir -p /var/backups/npanel
cp /var/www/npanel/database/database.sqlite /var/backups/npanel/database_backup.sqlite
cp /var/www/npanel/.env /var/backups/npanel/.env_backup

# 2. Maintenance Mode
cd /var/www/npanel
php artisan down

# 3. Queue Workers stoppen
supervisorctl stop npanel-worker:*

# 4. Code aktualisieren
git pull origin main

# 5. Dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# 6. Datenbank
php artisan migrate --force

# 7. .env prüfen (falls neue Variablen hinzugekommen sind)
# Vergleiche mit .env.example

# 8. Caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# 9. Permissions
chown -R www-data:www-data /var/www/npanel
chmod -R 755 /var/www/npanel
chmod -R 775 /var/www/npanel/storage
chmod -R 775 /var/www/npanel/bootstrap/cache

# 10. Services
systemctl reload nginx
supervisorctl start npanel-worker:*

# 11. Maintenance Mode deaktivieren
php artisan up
```

---

## Wichtige Pfade

```
Panel Installation:     /var/www/npanel
Nginx Config:           /etc/nginx/sites-available/npanel.conf
Backups:                /var/backups/npanel
Logs:                   /var/www/npanel/storage/logs/laravel.log
Database:               /var/www/npanel/database/database.sqlite
Environment:            /var/www/npanel/.env
Queue Worker Config:    /etc/supervisor/conf.d/npanel-worker.conf
```

---

## Nützliche Befehle

```bash
# Status prüfen
supervisorctl status                          # Queue Workers
systemctl status nginx                        # Nginx
systemctl status php8.3-fpm                   # PHP-FPM

# Logs ansehen
tail -f /var/www/npanel/storage/logs/laravel.log
tail -f /var/log/nginx/error.log

# Queue Worker neustarten
supervisorctl restart npanel-worker:*

# Cache leeren (falls Probleme auftreten)
cd /var/www/npanel
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Datenbank Backup erstellen
cp /var/www/npanel/database/database.sqlite /root/database_backup_$(date +%Y%m%d).sqlite
```

---

## Troubleshooting

### Update schlägt fehl: "Could not resolve host"
```bash
# DNS prüfen
ping github.com
# Falls nicht erreichbar: DNS Server in /etc/resolv.conf anpassen
echo "nameserver 8.8.8.8" > /etc/resolv.conf
```

### Permissions Fehler nach Update
```bash
cd /var/www/npanel
chown -R www-data:www-data .
chmod -R 755 .
chmod -R 775 storage bootstrap/cache database
```

### Queue Workers laufen nicht
```bash
supervisorctl reread
supervisorctl update
supervisorctl start npanel-worker:*
```

### "Base table or column not found" nach Update
```bash
cd /var/www/npanel
php artisan migrate --force
php artisan cache:clear
```

### Frontend zeigt alte Version
```bash
cd /var/www/npanel
npm run build
php artisan view:clear
# Browser Cache leeren (Strg+Shift+R)
```

---

## Support

Bei Problemen:
1. Logs prüfen: `/var/www/npanel/storage/logs/laravel.log`
2. GitHub Issues: https://github.com/marion909/npanel/issues
3. Backup wiederherstellen (siehe oben)
