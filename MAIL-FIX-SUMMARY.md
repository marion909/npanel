# Mail-Server-Fix Zusammenfassung

## ✅ Durchgeführte Änderungen

### 1. Code-Updates
- **PostfixService.php**: Verwendet jetzt `env('MAIL_DB_*')` statt `config('database.connections.mysql.*')`
- **DovecotService.php**: Verwendet jetzt `env('MAIL_DB_*')` statt `config('database.connections.mysql.*')`
- **config/database.php**: Neue `mail` Verbindung hinzugefügt
- **.env**: `MAIL_DB_*` Variablen hinzugefügt

### 2. Neue Artisan Commands
- **`php artisan mail:sync-database`**: Synchronisiert Daten von SQLite nach MySQL Mail-DB
- **`php artisan mail:regenerate-configs`**: Regeneriert Postfix/Dovecot Konfigurationen

### 3. Installations-/Update-Skripte erweitert

#### install.sh
- ✅ Neue Funktion `setup_mail_database()` erstellt Mail-DB automatisch
- ✅ Wird beim Mail-Server-Setup automatisch aufgerufen
- ✅ Synchronisiert Daten nach Installation
- ✅ Verwendet neue Artisan-Commands

#### update.sh
- ✅ Prüft automatisch, ob Mail-DB existiert
- ✅ Richtet Mail-DB bei Bedarf automatisch ein
- ✅ Synchronisiert Daten bei jedem Update
- ✅ Regeneriert Configs automatisch

## 🚀 Was passiert jetzt beim Update?

### Bei Neuinstallation (install.sh)
1. Fragt, ob Mail-Server installiert werden soll
2. Erstellt automatisch `npanel_mail` Datenbank
3. Generiert sicheres Passwort
4. Erstellt Tabellen (domains, mailboxes, mail_aliases)
5. Fügt Credentials zu .env hinzu
6. Installiert Postfix/Dovecot/OpenDKIM
7. Synchronisiert Daten
8. Generiert Configs

### Bei Update (update.sh)
1. Erstellt Backup
2. Zieht neuesten Code
3. Prüft `.env` auf fehlende Variablen
4. **NEU**: Erkennt fehlende `MAIL_DB_*` Einträge
5. **NEU**: Richtet Mail-DB automatisch ein (falls Mail-Server installiert)
6. Synchronisiert Daten zu Mail-DB
7. Regeneriert Postfix/Dovecot Configs
8. Startet Services neu

## 📋 Nächste Schritte auf dem Server

### Sofort-Lösung
```bash
ssh root@49.13.168.95
cd /var/www/npanel
./update.sh
```

Das war's! Das Update-Skript macht alles automatisch.

### Manuell (falls gewünscht)
```bash
# 1. Code aktualisieren
git pull

# 2. Dependencies
composer install --no-dev --optimize-autoloader

# 3. .env prüfen - sollte jetzt MAIL_DB_* enthalten
cat .env | grep MAIL_DB

# 4. Wenn nicht vorhanden, update.sh laufen lassen
./update.sh

# 5. Oder manuell:
php artisan config:clear
php artisan mail:sync-database
php artisan mail:regenerate-configs

# 6. Services neu starten
systemctl restart postfix dovecot
```

## 🔍 Testen

```bash
# Mail-DB-Verbindung testen
mysql -u npanel_mail -p npanel_mail
# (Passwort aus .env MAIL_DB_PASSWORD)

# Postfix Domain-Lookup
postmap -q deine-domain.de mysql:/etc/postfix/mysql/virtual-domains.cf

# Dovecot Config testen
doveconf -n

# Logs überwachen
tail -f /var/log/mail.log
```

## 📚 Dokumentation

- **MAIL-FIX-QUICKSTART.md**: Schnellanleitung für Reparatur
- **MAIL-SERVER-SETUP.md**: Ausführliche technische Dokumentation
- **setup-mail-database.sh**: Standalone-Skript für Mail-DB-Setup

## ✨ Vorteile der neuen Lösung

1. **Automatisch**: Update-Skript richtet alles selbst ein
2. **Sicher**: Generiert starke Passwörter automatisch
3. **Getrennt**: Laravel nutzt SQLite, Mail-Server MySQL
4. **Wartbar**: Klare Trennung der Konfigurationen
5. **Synchron**: Daten werden automatisch synchronisiert
6. **Fehlertoleranz**: Skripte prüfen Voraussetzungen
7. **Rückwärtskompatibel**: Bestehende Installationen werden erkannt

## 🎯 Problem gelöst

❌ **Vorher**: 
- Mail-Server suchte MySQL-Config in leerem `config('database.connections.mysql.*')`
- Keine Datenbankverbindung = kein Mail-Server

✅ **Jetzt**:
- Mail-Server verwendet dedizierte `env('MAIL_DB_*')` Variablen
- Update-Skript richtet alles automatisch ein
- Synchronisation läuft automatisch

## 🔄 Automatische Synchronisation (Optional)

Für Echtzeit-Sync zwischen SQLite und MySQL:

```bash
# Cron-Job erstellen
crontab -e

# Füge hinzu (synct alle 5 Minuten)
*/5 * * * * cd /var/www/npanel && php artisan mail:sync-database > /dev/null 2>&1
```

Oder: Implementiere Event-Listener, die bei Domain/Mailbox-Änderungen auch die Mail-DB aktualisieren.
