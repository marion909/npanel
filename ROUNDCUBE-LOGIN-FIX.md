# Roundcube Login-Problem beheben

## Problem
Roundcube-Login funktioniert nicht mit E-Mail-Adresse und Passwort.

## Ursache
Die Roundcube-Konfiguration hatte falsche IMAP/SMTP-Einstellungen:
- ❌ Versuchte SSL/TLS-Verbindung zu localhost (Port 993/587 SSL)
- ❌ Dovecot/Postfix sind nicht für SSL auf localhost konfiguriert
- ❌ Fehlende SSL-Verifikations-Einstellungen

## Lösung

### ✅ Was wurde korrigiert:

**IMAP-Einstellungen:**
```php
// ALT (falsch):
$config['default_host'] = 'ssl://localhost';
$config['default_port'] = 993;

// NEU (korrekt):
$config['default_host'] = 'localhost';
$config['default_port'] = 143;  // IMAP ohne SSL
$config['imap_conn_options'] = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
];
```

**SMTP-Einstellungen:**
```php
// ALT (falsch):
$config['smtp_host'] = 'tls://localhost';
$config['smtp_port'] = 587;

// NEU (korrekt):
$config['smtp_host'] = 'localhost:587';
$config['smtp_conn_options'] = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
];
```

## Auf dem Server anwenden

### Option 1: Automatisches Update (Empfohlen)
```bash
ssh root@49.13.168.95
cd /var/www/npanel

# Code aktualisieren
git pull

# Update ausführen - regeneriert Roundcube-Config automatisch
./update.sh
```

### Option 2: Manuelle Konfiguration

```bash
ssh root@49.13.168.95

# Roundcube-Config bearbeiten
nano /var/www/roundcube/config/config.inc.php
```

Ersetze die IMAP/SMTP-Einstellungen:

```php
<?php
$config = [];

// Database connection (UNVERÄNDERT LASSEN)
$config['db_dsnw'] = 'mysql://...';

// IMAP settings - NEU
$config['default_host'] = 'localhost';
$config['default_port'] = 143;
$config['imap_conn_options'] = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
];
$config['imap_auth_type'] = null;
$config['imap_delimiter'] = '/';

// SMTP settings - NEU
$config['smtp_host'] = 'localhost:587';
$config['smtp_user'] = '%u';
$config['smtp_pass'] = '%p';
$config['smtp_conn_options'] = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
];

// Security (UNVERÄNDERT LASSEN)
$config['des_key'] = '...';
$config['cipher_method'] = 'AES-256-CBC';
$config['username_domain'] = '';

// UI (UNVERÄNDERT LASSEN)
$config['product_name'] = 'nPanel Webmail';
$config['support_url'] = '';
$config['skin'] = 'elastic';
$config['language'] = 'en_US';

// Identities
$config['identities_level'] = 0;

// Misc (UNVERÄNDERT LASSEN)
$config['enable_installer'] = false;
$config['log_driver'] = 'syslog';
$config['syslog_facility'] = LOG_MAIL;
$config['session_lifetime'] = 30;

// Plugins
$config['plugins'] = [];
```

Speichern: `CTRL+O`, `Enter`, `CTRL+X`

## Test

### 1. Mailbox erstellen (falls noch nicht vorhanden)
```bash
# Via nPanel Web-Interface oder:
cd /var/www/npanel
php artisan tinker

# In tinker:
$domain = \App\Models\Domain::where('domain_name', 'deine-domain.de')->first();
$mailService = app(\App\Services\MailService::class);
$mailbox = $mailService->createMailbox($domain, 'test', 'DeinPasswort123!', 1000);
echo "Created: " . $mailbox->email;
exit
```

### 2. Dovecot-Auth testen
```bash
# Teste ob Dovecot die Mailbox findet
doveadm auth test test@deine-domain.de DeinPasswort123!

# Sollte ausgeben:
# passdb: test@deine-domain.de auth succeeded
# userdb: test@deine-domain.de
```

### 3. Roundcube-Login testen
1. Öffne: `https://webmail.deine-domain.de`
2. Username: `test@deine-domain.de`
3. Password: `DeinPasswort123!`
4. Login klicken

### 4. Logs überwachen
```bash
# Terminal 1: Mail-Logs
tail -f /var/log/mail.log

# Terminal 2: Dovecot-Logs
tail -f /var/log/dovecot.log

# Terminal 3: Roundcube-Logs
tail -f /var/log/syslog | grep roundcube
```

## Häufige Fehler

### "Connection to storage server failed"
**Ursache**: Dovecot läuft nicht oder falsche Config
```bash
# Status prüfen
systemctl status dovecot

# Neu starten
systemctl restart dovecot

# Config testen
doveconf -n

# Auth-Socket prüfen
ls -la /var/run/dovecot/auth-userdb
```

### "AUTHENTICATE failed"
**Ursache**: Mailbox existiert nicht oder falsches Passwort
```bash
# In Mail-DB prüfen
mysql -u npanel_mail -p npanel_mail -e "SELECT email, status FROM mailboxes;"

# Passwort neu setzen
cd /var/www/npanel
php artisan tinker

$mailbox = \App\Models\Mailbox::where('email', 'test@domain.de')->first();
$mailService = app(\App\Services\MailService::class);
$mailService->updateMailbox($mailbox, 'NeuesPasswort123!');
```

### "Invalid username or password"
**Ursache**: 
1. Mailbox in DB, aber Dovecot findet sie nicht
2. Mail-DB nicht synchronisiert

```bash
# Mail-DB synchronisieren
cd /var/www/npanel
php artisan mail:sync-database

# Dovecot neu starten
systemctl restart dovecot

# Nochmal testen
doveadm auth test deine@email.de passwort
```

### "SMTP Authentication failed"
**Ursache**: Postfix SASL nicht konfiguriert oder Dovecot auth-Socket fehlt

```bash
# Postfix SASL-Einstellungen prüfen
postconf | grep smtpd_sasl

# Sollte enthalten:
# smtpd_sasl_auth_enable = yes
# smtpd_sasl_type = dovecot
# smtpd_sasl_path = private/auth

# Auth-Socket prüfen
ls -la /var/spool/postfix/private/auth

# Falls nicht vorhanden, Dovecot Master-Config prüfen
cat /etc/dovecot/conf.d/10-master.conf | grep -A 10 "service auth"

# Configs neu generieren
php artisan mail:regenerate-configs
systemctl restart postfix dovecot
```

## Dovecot/Postfix Ports prüfen

```bash
# IMAP Port 143 (unverschlüsselt, nur localhost)
netstat -tlnp | grep :143

# IMAP Port 993 (SSL, optional)
netstat -tlnp | grep :993

# SMTP Port 587 (submission)
netstat -tlnp | grep :587

# SMTP Port 25 (eingehend)
netstat -tlnp | grep :25
```

## Roundcube Debug-Modus

Falls weiterhin Probleme:

```bash
nano /var/www/roundcube/config/config.inc.php
```

Füge hinzu:
```php
$config['debug_level'] = 4;
$config['log_driver'] = 'file';
$config['log_dir'] = '/var/www/roundcube/logs/';
```

Logs ansehen:
```bash
mkdir -p /var/www/roundcube/logs
chown www-data:www-data /var/www/roundcube/logs
tail -f /var/www/roundcube/logs/errors.log
```

## Zusammenfassung

✅ **install.sh** und **update.sh** korrigiert
✅ Roundcube nutzt jetzt **unverschlüsselte IMAP/SMTP zu localhost**
✅ SSL-Verifizierung deaktiviert für localhost
✅ Korrekte Dovecot/Postfix-Anbindung

**Nächster Schritt auf dem Server:**
```bash
ssh root@49.13.168.95
cd /var/www/npanel
git pull
./update.sh
```

Danach sollte Roundcube-Login funktionieren!
