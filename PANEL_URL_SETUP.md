# Panel URL Setup Guide

## Übersicht

Das nPanel kann nun über eine eigene Domain mit HTTPS-Zertifikat erreicht werden, anstatt nur über die Server-IP.

## Voraussetzungen

1. Eine Domain (z.B. `panel.npanel.at`)
2. DNS A-Record, der auf die Server-IP zeigt
3. Root-Zugriff auf den Server

## Automatisches Setup via Artisan Command

Der einfachste Weg ist der Artisan Command:

```bash
php artisan npanel:setup-domain panel.yourdomain.com
```

Dieser Command führt automatisch aus:
1. ✓ DNS-Überprüfung
2. ✓ Nginx-Konfiguration generieren
3. ✓ Nginx Config testen
4. ✓ Nginx neu laden
5. ✓ SSL-Zertifikat ausstellen (Let's Encrypt)
6. ✓ .env Datei aktualisieren

## Manuelle Konfiguration

Falls du die Schritte manuell durchführen möchtest:

### 1. DNS konfigurieren

Erstelle einen A-Record für deine Domain:
```
panel.yourdomain.com  →  A  →  49.13.168.95
```

Warte bis DNS propagiert ist (1-48 Stunden):
```bash
nslookup panel.yourdomain.com
```

### 2. Nginx vHost erstellen

Datei: `/etc/nginx/sites-available/panel.yourdomain.com.conf`

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name panel.yourdomain.com;

    root /var/www/npanel/public;
    index index.php index.html;

    location ^~ /.well-known/acme-challenge/ {
        allow all;
        default_type "text/plain";
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

Symlink erstellen:
```bash
sudo ln -s /etc/nginx/sites-available/panel.yourdomain.com.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 3. SSL-Zertifikat ausstellen

```bash
sudo /root/.acme.sh/acme.sh --issue -d panel.yourdomain.com -w /var/www/npanel/public
```

Zertifikat installieren:
```bash
sudo mkdir -p /etc/letsencrypt/live/panel.yourdomain.com

sudo /root/.acme.sh/acme.sh --install-cert -d panel.yourdomain.com --ecc \
  --cert-file /etc/letsencrypt/live/panel.yourdomain.com/cert.pem \
  --key-file /etc/letsencrypt/live/panel.yourdomain.com/privkey.pem \
  --fullchain-file /etc/letsencrypt/live/panel.yourdomain.com/fullchain.pem \
  --reloadcmd "systemctl reload nginx"
```

### 4. HTTPS in Nginx aktivieren

Nginx Config erweitern:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name panel.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name panel.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/panel.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/panel.yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    root /var/www/npanel/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

Nginx neu laden:
```bash
sudo nginx -t
sudo systemctl reload nginx
```

### 5. Panel Settings aktualisieren

1. Gehe zu **Settings > System**
2. Setze **Panel URL (HTTPS)** auf: `https://panel.yourdomain.com`
3. Speichern

Oder via `.env`:
```bash
echo "NPANEL_URL=https://panel.yourdomain.com" >> /var/www/npanel/.env
cd /var/www/npanel
php artisan config:clear
```

## Testen

Teste den Zugriff:
```bash
curl -I https://panel.yourdomain.com
```

Erwartete Ausgabe:
```
HTTP/2 200
server: nginx
...
```

Browser-Test:
1. Öffne https://panel.yourdomain.com
2. Login sollte funktionieren
3. Kein SSL-Warnhinweis

## Troubleshooting

### DNS zeigt nicht auf Server
```bash
# Prüfe DNS
dig panel.yourdomain.com +short

# Sollte deine Server-IP zeigen
# Falls nicht: Warte oder DNS korrigieren
```

### SSL-Fehler
```bash
# Zertifikat prüfen
sudo /root/.acme.sh/acme.sh --list

# Zertifikat manuell erneuern
sudo /root/.acme.sh/acme.sh --renew -d panel.yourdomain.com --ecc --force
```

### Nginx Config Fehler
```bash
# Config testen
sudo nginx -t

# Logs ansehen
sudo tail -f /var/log/nginx/error.log
```

### 502 Bad Gateway
```bash
# PHP-FPM Status prüfen
sudo systemctl status php8.3-fpm

# PHP-FPM neu starten
sudo systemctl restart php8.3-fpm
```

## Sicherheit

- ✅ Nur HTTPS erlaubt (keine HTTP-Panel-URL)
- ✅ Let's Encrypt Zertifikat (90 Tage gültig, Auto-Renewal)
- ✅ TLS 1.2 und 1.3
- ✅ Strong Ciphers

## Alte IP-basierte Zugriffe

Der alte IP-basierte Zugriff (http://49.13.168.95) funktioniert weiterhin parallel.

Um ihn zu deaktivieren, entferne oder deaktiviere die IP-basierte Nginx-Config:
```bash
sudo rm /etc/nginx/sites-enabled/npanel.conf
sudo systemctl reload nginx
```

## Auto-Renewal

Let's Encrypt Zertifikate werden automatisch erneuert via Cron Job:
```bash
# Cron Job prüfen
sudo crontab -l | grep acme

# Sollte etwa so aussehen:
# 0 0 * * * /root/.acme.sh/acme.sh --cron
```
