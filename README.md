# NPanel

> Laravel-basiertes Multidomain Control Panel mit automatischer Nginx-Konfiguration, Hetzner DNS Integration und Wildcard SSL-Verwaltung

## Features

- 🌐 **Multidomain-Verwaltung** - Domains und unbegrenzte Subdomains pro User
- 🔒 **Wildcard SSL** - Automatische Let's Encrypt Zertifikate via DNS-01 Challenge
- 🌍 **Hetzner DNS API** - Vollständige DNS-Record-Verwaltung
- ⚙️ **Nginx Auto-Config** - Dynamische Server-Block-Generierung
- 🐘 **Multi-PHP Support** - PHP 7.4, 8.0, 8.1, 8.2, 8.3 pro Domain/Subdomain
- 📊 **Logging & Rollback** - Vollständige Änderungshistorie für Nginx & DNS
- 🎨 **Modern UI** - Vue 3 + Inertia.js + Tailwind CSS
- 🔐 **Laravel Breeze** - Authentifizierung mit Session-Management

## Schnellstart

### Entwicklung (Windows/Local)

```powershell
git clone https://github.com/marion909/npanel.git
cd npanel
composer install
npm install --legacy-peer-deps
copy .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Öffne http://localhost:8000

### Production (Linux Server)

```bash
cd /var/www
git clone https://github.com/marion909/npanel.git
cd npanel
cp .env.example .env
nano .env  # Konfigurieren: DB, Hetzner Token, etc.
chmod +x deploy.sh
./deploy.sh --first-time
```

Vollständige Anleitung: [DEPLOYMENT.md](DEPLOYMENT.md)

## Systemanforderungen

- **PHP** 8.2+
- **MySQL/MariaDB** 10.4+
- **Node.js** 18+
- **Nginx**
- **Composer** 2.x
- **Certbot** mit dns-hetzner Plugin
- **Hetzner DNS** API Token

## Technologie-Stack

| Komponente | Technologie |
|------------|-------------|
| Framework | Laravel 11.x |
| Frontend | Vue 3 + Inertia.js |
| Styling | Tailwind CSS |
| Auth | Laravel Breeze |
| HTTP Client | Guzzle |
| DNS API | Hetzner DNS |
| SSL | Let's Encrypt (Certbot) |
| Webserver | Nginx |
| Database | MySQL/MariaDB |

## Projektstruktur

```
npanel/
├── app/
│   ├── Console/Commands/      # Artisan Commands
│   ├── Http/Controllers/      # API & Inertia Controller
│   ├── Models/                # Eloquent Models
│   ├── Observers/             # Domain & Subdomain Lifecycle
│   └── Services/              # Core Business Logic
├── database/migrations/       # DB Schema
├── resources/
│   ├── js/Pages/             # Vue Components (Inertia)
│   └── nginx/                # Nginx Config Templates
├── routes/
│   └── web.php               # API & Inertia Routes
├── deploy.sh                 # Production Deployment
├── update-dev.bat/.sh        # Development Updates
└── DEPLOYMENT.md             # Setup-Anleitung
```

## Wichtige Artisan Commands

```bash
# System-Setup
php artisan system:install

# Hetzner DNS
php artisan hetzner:zone-scan     # Zones für Domains finden
php artisan hetzner:sync          # DNS Records synchronisieren

# SSL Zertifikate
php artisan ssl:issue example.com # Wildcard SSL für Domain
php artisan ssl:renew             # Alle SSL erneuern
```

## Konfiguration (.env)

```env
# Hetzner DNS API
HETZNER_DNS_API_BASE=https://dns.hetzner.com/api
HETZNER_DNS_API_TOKEN=your_token_here

# Wildcard SSL
WILDCARD_SSL_EMAIL=admin@example.com
WILDCARD_SSL_PROVIDER=letsencrypt

# Server Pfade
NGINX_SITES_AVAILABLE=/etc/nginx/sites-available
NGINX_SITES_ENABLED=/etc/nginx/sites-enabled
PHP_FPM_SOCKETS_PATH=/run/php
DOCUMENT_ROOT_BASE=/var/www
APP_SERVER_IPV4=your_server_ip
```

## Updates

**Production:**
```bash
cd /var/www/npanel
./deploy.sh
```

**Development:**
```powershell
.\update-dev.bat
```

## Architektur

### Domain Lifecycle
1. **Domain erstellen** → Hetzner Zone Scan → Verification TXT Record
2. **DNS Validierung** → Status: active
3. **Wildcard SSL** → Certbot DNS-01 Challenge
4. **Nginx Config** → Template Rendering → Sites-Available → Symlink → Reload
5. **Document Root** → Automatische Ordner-Erstellung

### Observer Pattern
- `DomainObserver`: DNS Setup, SSL Request, Nginx Deploy
- `SubdomainObserver`: DNS Records, Document Roots, Nginx Config

### Service Layer
- `HetznerDnsService`: API Client, Zone Management, Record CRUD
- `WildcardSslService`: Certbot Integration, Renewal
- `DnsValidationService`: Domain Verification
- `NginxService`: Config Generation, Deployment
- `PhpFpmService`: Socket Path Resolution
- `DocumentRootService`: Directory Management

## API Endpoints

| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| GET | `/api/domains` | Domains auflisten |
| POST | `/api/domains` | Domain erstellen |
| GET | `/api/domains/{id}` | Domain Details |
| PATCH | `/api/domains/{id}` | Domain aktualisieren |
| DELETE | `/api/domains/{id}` | Domain löschen |
| POST | `/api/domains/{id}/verify` | DNS Verifizierung |
| POST | `/api/domains/{id}/request-wildcard` | SSL anfordern |
| GET/POST/DELETE | `/api/domains/{id}/subdomains` | Subdomain CRUD |
| GET/POST/DELETE | `/api/domains/{id}/records` | DNS Record CRUD |
| GET | `/api/domains/{id}/nginx-logs` | Nginx Config Logs |
| GET | `/api/domains/{id}/hetzner-logs` | Hetzner API Logs |

## Datenbank Schema

```sql
domains (id, user_id, name, status, hetzner_zone_id, wildcard_ssl_enabled, php_version, document_root)
subdomains (id, domain_id, name, full_name, php_version, document_root, nginx_enabled)
dns_records (id, domain_id, subdomain_id, hetzner_record_id, type, name, value, ttl, status)
nginx_config_logs (id, domain_id, subdomain_id, action, previous_config, new_config, success)
hetzner_api_logs (id, domain_id, subdomain_id, method, endpoint, request_payload, response_code, success)
```

## Scheduled Tasks

```bash
# Crontab eintragen:
* * * * * cd /var/www/npanel && php artisan schedule:run >> /dev/null 2>&1
```

**Geplante Jobs:**
- `ssl:renew` - Täglich (SSL-Erneuerung)
- `hetzner:sync` - Stündlich (DNS-Synchronisation)

## Sicherheit

- ✅ Laravel Sanctum für API
- ✅ CSRF Protection
- ✅ SQL Injection Prevention (Eloquent)
- ✅ XSS Protection (Vue/Inertia)
- ✅ SSL Enforcement (Let's Encrypt)
- ✅ User Authorization (Policy-based)
- ⚠️ `.env` niemals committen
- ⚠️ `APP_DEBUG=false` in Production

## Troubleshooting

```bash
# Logs prüfen
tail -f storage/logs/laravel.log
php artisan pail

# Cache löschen
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## Roadmap

- [ ] IPv6 Support
- [ ] Multi-Server Management
- [ ] Backup & Restore
- [ ] DNS Zone Export/Import
- [ ] Email Notifications
- [ ] 2FA Authentication
- [ ] API Rate Limiting
- [ ] Terraform Integration

## Lizenz

MIT License - siehe [LICENSE](LICENSE)

## Support

- 📖 [Deployment Guide](DEPLOYMENT.md)
- 🐛 [Issues](https://github.com/marion909/npanel/issues)
- 💬 [Discussions](https://github.com/marion909/npanel/discussions)

## Credits

Entwickelt mit ❤️ unter Verwendung von:
- [Laravel](https://laravel.com)
- [Vue.js](https://vuejs.org)
- [Inertia.js](https://inertiajs.com)
- [Tailwind CSS](https://tailwindcss.com)
- [Hetzner DNS API](https://dns.hetzner.com/api-docs)
