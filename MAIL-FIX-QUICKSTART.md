# Schnellanleitung: Mail-Server reparieren

## Problem
Der Mail-Server funktioniert nicht, weil die Datenbankverbindung fehlt.

## Lösung - Automatisch mit update.sh

### Option A: Automatisches Update (Empfohlen)
```bash
ssh root@49.13.168.95
cd /var/www/npanel  # oder dein Installationspfad

# Update durchführen - richtet Mail-DB automatisch ein
./update.sh
```

Das Update-Skript:
- ✅ Erkennt fehlende Mail-DB-Konfiguration automatisch
- ✅ Erstellt Mail-Datenbank und Tabellen
- ✅ Generiert sichere Passwörter
- ✅ Aktualisiert .env automatisch
- ✅ Synchronisiert Daten
- ✅ Regeneriert Postfix/Dovecot Configs
- ✅ Startet Services neu

### Option B: Manuelles Setup

Falls du es manuell machen möchtest:

#### 1. Auf den Server verbinden
```bash
ssh root@49.13.168.95
cd /var/www/npanel  # oder dein Installationspfad
```

#### 2. Mail-Datenbank erstellen (falls setup-mail-database.sh existiert)
```bash
chmod +x setup-mail-database.sh
./setup-mail-database.sh
```

ODER direkt via MySQL:

```bash
# Sichere Passwörter generieren
MAIL_DB_PASS=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-25)
echo "Generated password: $MAIL_DB_PASS"

# MySQL Admin Credentials aus .env holen
MYSQL_USER=$(grep MYSQL_ROOT_USERNAME .env | cut -d'=' -f2)
MYSQL_PASS=$(grep MYSQL_ROOT_PASSWORD .env | cut -d'=' -f2)

# Datenbank erstellen
mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" <<EOF
CREATE DATABASE IF NOT EXISTS npanel_mail CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'npanel_mail'@'localhost' IDENTIFIED BY '$MAIL_DB_PASS';
GRANT ALL PRIVILEGES ON npanel_mail.* TO 'npanel_mail'@'localhost';
FLUSH PRIVILEGES;
EOF
```

#### 3. .env aktualisieren
```bash
nano .env
```

Füge am Ende hinzu:
```env
# Mail Server Database Configuration
MAIL_DB_HOST=127.0.0.1
MAIL_DB_PORT=3306
MAIL_DB_DATABASE=npanel_mail
MAIL_DB_USERNAME=npanel_mail
MAIL_DB_PASSWORD=<das_generierte_passwort>
```

Speichern: `CTRL+O`, `Enter`, `CTRL+X`

#### 4. Daten synchronisieren und Configs neu generieren
```bash
# Cache leeren
php artisan config:clear
php artisan cache:clear

# Daten von SQLite nach MySQL kopieren
php artisan mail:sync-database

# Postfix/Dovecot Configs neu erstellen
php artisan mail:regenerate-configs
```

### 5. Mail-Services neu starten
```bash
systemctl restart postfix
systemctl restart dovecot
```

## Test
```bash
# Domain-Lookup testen
postmap -q deine-domain.de mysql:/etc/postfix/mysql/virtual-domains.cf

# Mailbox-Lookup testen (wenn Mailbox existiert)
postmap -q user@deine-domain.de mysql:/etc/postfix/mysql/virtual-mailboxes.cf

# Logs überprüfen
tail -f /var/log/mail.log
```

## Häufige Probleme

### "Access denied for user 'npanel_mail'"
→ Prüfe, ob das Passwort in `.env` korrekt ist (aus Schritt 2)
→ Teste: `mysql -u npanel_mail -p npanel_mail`

### "Unknown database 'npanel_mail'"
→ Schritt 2 nicht korrekt durchgeführt
→ Prüfe: `mysql -u root -p -e "SHOW DATABASES;"`

### Postfix/Dovecot starten nicht
→ Teste Configs: `postfix check` und `doveconf -n`
→ Logs: `journalctl -u postfix -n 50` und `journalctl -u dovecot -n 50`

### Keine Daten in Mail-DB
→ Schritt 4 vergessen
→ Nochmal laufen lassen: `php artisan mail:sync-database`

## Automatische Synchronisation (Optional)

Damit neue Domains/Mailboxes automatisch synchronisiert werden:

```bash
crontab -e
```

Füge hinzu:
```
*/5 * * * * cd /pfad/zum/npanel && php artisan mail:sync-database > /dev/null 2>&1
```

## Hilfe?

Logs ansehen:
```bash
tail -f /var/log/mail.log          # Postfix/Dovecot
tail -f storage/logs/laravel.log   # nPanel Logs
```

Dienste-Status:
```bash
systemctl status postfix
systemctl status dovecot
systemctl status opendkim
```
