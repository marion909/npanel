# NPanel - Master Implementation Plan

## Project Overview

Ein vollständiges Laravel-basiertes Control-Panel mit Inertia.js/Vue 3, automatischer Nginx-Konfiguration, **Hetzner DNS API Integration**, **Wildcard SSL-Zertifikate via DNS-01 Challenge**, Shared PHP-FPM-Pools, Rollback-Mechanismus, Single-Admin-Authentication und vollständiger Änderungshistorie.

## Core Features

### 1. Domain Management mit Hetzner DNS Integration
- CRUD-Operationen für Domains
- **Automatische Verifikation via Hetzner DNS API** (prüft ob Domain dem User zugeordnet ist)
- **Import vorhandener DNS-Records** aus Hetzner-Zone
- DNS-Validierung (IPv4 A-Records)
- Status-Tracking (DNS verified, Hetzner-verified, aktiv/inaktiv)
- **Wildcard-SSL-Option** pro Domain
- User-Ownership (Single-Admin-User)

### 2. Subdomain Management mit automatischer DNS-Erstellung
- CRUD-Operationen für Subdomains
- **Automatische DNS-Record-Erstellung in Hetzner DNS** (A-Record mit Server-IPv4)
- **Automatische DNS-Record-Löschung** beim Entfernen von Subdomains
- **Synchronisation mit Hetzner DNS API** bei Änderungen
- Individuelles Document-Root pro Subdomain
- PHP-Versionswahl (7.4, 8.0, 8.1, 8.2, 8.3)
- Shared PHP-FPM-Pools pro PHP-Version
- **Automatische SSL-Nutzung von Wildcard-Zertifikat** (falls Domain SSL aktiviert hat)

### 3. Nginx Configuration Management
- Automatische Generierung von Nginx-Configs
- Catch-all für nicht registrierte Domains
- 404-Seite für Domains ohne Subdomains
- Subdomain-Configs mit/ohne SSL
- **Wildcard-SSL-Template** für alle Subdomains einer Domain
- Automatisches Reload nach Änderungen
- Rollback bei fehlgeschlagenen Configs

### 4. Change Tracking & Logging
- Vollständige Historie aller Config-Änderungen
- Config-Backups für Rollback
- Status-Tracking (success/failed)
- Error-Messages
- **DNS-Change-Tracking** (Hetzner API Calls)
- **Hetzner API Logs** mit Request/Response-Details
- UI zur Einsicht aller Logs

### 5. Wildcard SSL/HTTPS Automation via DNS-01
- **Let's Encrypt Wildcard-Zertifikate** für `*.domain.com`
- **DNS-01 Challenge via Hetzner DNS API** (automatische TXT-Record-Erstellung)
- **Certbot DNS-Plugin Integration** (`certbot-dns-hetzner`)
- Automatische Zertifikat-Erstellung für alle Subdomains
- Auto-Renewal via Scheduler
- SSL-Status-Anzeige in UI

## Technical Stack

### Backend
- **Framework**: Laravel 11.x
- **Authentication**: Laravel Breeze mit Inertia-Stack
- **Database**: MySQL/MariaDB
- **HTTP-Client**: Guzzle (für Hetzner DNS API)
- **Queue**: Redis (optional, für Background-Jobs)

### Frontend
- **Framework**: Vue 3
- **Stack**: Inertia.js
- **Styling**: Tailwind CSS (via Breeze)

### Server Components
- **Web Server**: Nginx
- **PHP**: PHP-FPM mit mehreren Versionen (7.4-8.3)
- **SSL**: Certbot mit DNS-Hetzner-Plugin
- **DNS**: Hetzner DNS API

## Complete Database Schema

### Table: `users`
```sql
id (bigint, PK)
name (string)
email (string, unique)
password (string)
created_at (timestamp)
updated_at (timestamp)
```

### Table: `domains`
```sql
id (bigint, PK)
user_id (bigint, FK -> users.id)
domain_name (string, unique)
hetzner_zone_id (string, nullable) -- Hetzner DNS Zone ID
hetzner_verified_at (timestamp, nullable) -- Zeitpunkt der Hetzner-Verifikation
ipv4_address (string, nullable) -- Server IPv4
dns_verified_at (timestamp, nullable)
wildcard_ssl_enabled (boolean, default: false) -- Wildcard SSL aktiv
wildcard_ssl_cert_path (string, nullable) -- /etc/letsencrypt/live/domain.com/fullchain.pem
wildcard_ssl_key_path (string, nullable) -- /etc/letsencrypt/live/domain.com/privkey.pem
wildcard_ssl_issued_at (timestamp, nullable)
is_active (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)

INDEX(user_id)
INDEX(domain_name)
INDEX(hetzner_zone_id)
```

### Table: `subdomains`
```sql
id (bigint, PK)
domain_id (bigint, FK -> domains.id CASCADE)
subdomain_name (string) -- z.B. "www", "api", "admin"
document_root (string) -- /var/www/vhosts/{domain}/{subdomain}/
php_version (string) -- "7.4", "8.0", "8.1", "8.2", "8.3"
nginx_config_path (string) -- /etc/nginx/sites-available/npanel/domain-subdomain.conf
is_active (boolean, default: true)
created_at (timestamp)
updated_at (timestamp)

UNIQUE(domain_id, subdomain_name)
INDEX(domain_id)
```

### Table: `dns_records`
```sql
id (bigint, PK)
domain_id (bigint, FK -> domains.id CASCADE)
subdomain_id (bigint, FK -> subdomains.id CASCADE, nullable)
hetzner_record_id (string) -- ID des Records in Hetzner DNS
record_type (string) -- "A", "AAAA", "CNAME", "TXT", "MX", etc.
name (string) -- Record-Name (z.B. "www", "@", "_acme-challenge")
value (string) -- Record-Value (z.B. IP, Domain, TXT-Content)
ttl (integer, default: 3600)
is_managed (boolean, default: true) -- Von NPanel verwaltet oder importiert
created_at (timestamp)
updated_at (timestamp)

INDEX(domain_id)
INDEX(subdomain_id)
INDEX(hetzner_record_id)
```

### Table: `nginx_config_logs`
```sql
id (bigint, PK)
loggable_type (string) -- Domain oder Subdomain
loggable_id (bigint)
action (string) -- "created", "updated", "deleted"
config_path (string)
config_backup (text, nullable) -- Backup der alten Config
status (string) -- "success", "failed"
error_message (text, nullable)
user_id (bigint, FK -> users.id)
created_at (timestamp)

INDEX(loggable_type, loggable_id)
INDEX(status)
INDEX(created_at)
```

### Table: `hetzner_api_logs`
```sql
id (bigint, PK)
user_id (bigint, FK -> users.id)
action (string) -- "zone_get", "record_create", "record_update", "record_delete"
endpoint (string) -- API-Endpoint
request_payload (json, nullable)
response_status (integer) -- HTTP Status Code
response_body (json, nullable)
error_message (text, nullable)
created_at (timestamp)

INDEX(user_id)
INDEX(action)
INDEX(response_status)
INDEX(created_at)
```

## Complete Service Classes

### HetznerDnsService
**Location**: `app/Services/HetznerDnsService.php`

**Dependencies**: Guzzle HTTP Client

**Methods**:
- `getZones(): array` - Holt alle Zones des Users
- `getZone(string $zoneName): ?object` - Holt Zone by Name
- `verifyDomainOwnership(Domain $domain): bool` - Prüft ob Domain dem User gehört
- `getZoneRecords(string $zoneId): array` - Holt alle Records einer Zone
- `importZoneRecords(Domain $domain): int` - Importiert Records in DB
- `createRecord(string $zoneId, string $type, string $name, string $value, int $ttl = 3600): ?string` - Erstellt DNS-Record
- `updateRecord(string $recordId, array $data): bool` - Updated DNS-Record
- `deleteRecord(string $recordId): bool` - Löscht DNS-Record
- `createAcmeChallenge(Domain $domain, string $token): ?string` - Erstellt TXT-Record für DNS-01
- `deleteAcmeChallenge(string $recordId): bool` - Löscht Challenge-Record

**Configuration** (`config/hetzner.php`):
```php
return [
    'dns_api_token' => env('HETZNER_DNS_API_TOKEN'),
    'dns_api_base_url' => 'https://dns.hetzner.com/api/v1',
    'default_ttl' => 3600,
];
```

### WildcardSslService
**Location**: `app/Services/WildcardSslService.php`

**Methods**:
- `issueWildcardCertificate(Domain $domain): bool` - Erstellt Wildcard-Zertifikat via DNS-01
- `renewWildcardCertificates(): void` - Erneuert alle Wildcard-Zertifikate
- `getWildcardCertificatePaths(Domain $domain): array` - Gibt cert/key-Pfade zurück
- `deleteWildcardCertificate(Domain $domain): bool` - Löscht Wildcard-Zertifikat

**Certbot Command**:
```bash
certbot certonly --dns-hetzner \
  --dns-hetzner-credentials /etc/letsencrypt/hetzner.ini \
  --dns-hetzner-propagation-seconds 60 \
  -d example.com \
  -d *.example.com \
  --non-interactive --agree-tos \
  -m admin@example.com
```

### DnsValidationService
**Location**: `app/Services/DnsValidationService.php`

**Methods**:
- `validateDomain(Domain $domain): bool` - Prüft IPv4 A-Record via dns_get_record
- `validateWithHetzner(Domain $domain): bool` - Validierung via Hetzner API
- `getServerIpv4(): string` - Ermittelt Server-IP (curl -4 ifconfig.me)
- `updateDomainDnsStatus(Domain $domain): void` - Updated dns_verified_at
- `syncDnsRecords(Domain $domain): void` - Synchronisiert lokale DB mit Hetzner DNS
- `detectDnsChanges(Domain $domain): array` - Erkennt Änderungen

### NginxService
**Location**: `app/Services/NginxService.php`

**Methods**:
- `generateCatchallConfig(): void` - Erstellt 000-default-catchall.conf
- `generateDomain404Config(Domain $domain): void` - Config für Domain ohne Subdomains
- `generateSubdomainConfig(Subdomain $subdomain): string` - Erstellt Subdomain-Config
- `generateSubdomainWildcardSslConfig(Subdomain $subdomain): string` - Config mit Wildcard-SSL
- `backupConfig(string $path): ?string` - Liest alte Config für Backup
- `testConfig(): bool` - Führt `sudo nginx -t` aus
- `reloadNginx(): bool` - Führt `sudo systemctl reload nginx` aus
- `rollbackConfig(string $path, string $backup): void` - Restored alte Config
- `deleteConfig(Subdomain $subdomain): void` - Löscht Config und Symlink

**Templates**: Blade-Views in `resources/views/templates/nginx/`

### PhpFpmService
**Location**: `app/Services/PhpFpmService.php`

**Methods**:
- `generateSharedPool(string $phpVersion): void` - Erstellt Shared-Pool-Config
- `reloadPhpFpm(string $phpVersion): bool` - Reload spezifischer PHP-FPM-Service
- `verifyPoolExists(string $phpVersion): bool` - Prüft ob Pool existiert

### DocumentRootService
**Location**: `app/Services/DocumentRootService.php`

**Methods**:
- `createDocumentRoot(Subdomain $subdomain): void` - Erstellt Verzeichnis via sudo
- `createPlaceholderIndex(Subdomain $subdomain): void` - Kopiert index.html-Template
- `deleteDocumentRoot(Subdomain $subdomain): void` - Löscht Verzeichnis
- `getPlaceholderTemplate(): string` - Liest Template aus storage/templates/index.html

## Model Observers

### DomainObserver
**Location**: `app/Observers/DomainObserver.php`

**Events**:

#### `creating(Domain $domain)`
1. Hetzner DNS API: Zone suchen via `getZone($domain->domain_name)`
2. Ownership prüfen via `verifyDomainOwnership()`
3. Bei Erfolg: `hetzner_zone_id` und `hetzner_verified_at` setzen
4. Server-IPv4 ermitteln und in `ipv4_address` speichern
5. Bei Fehler: Exception werfen

#### `created(Domain $domain)`
1. DNS-Records importieren via `importZoneRecords()`
2. Wenn `wildcard_ssl_enabled = true`: Wildcard-Zertifikat erstellen
3. Log in `hetzner_api_logs` erstellen

#### `updated(Domain $domain)`
1. Wenn `wildcard_ssl_enabled` geändert:
   - Bei true→false: Wildcard-Zertifikat löschen
   - Bei false→true: Wildcard-Zertifikat erstellen
2. DNS-Synchronisation triggern
3. Alle Subdomains: Nginx-Configs neu generieren (SSL-Pfade aktualisieren)

#### `deleted(Domain $domain)`
1. Alle Subdomains löschen (CASCADE)
2. Alle von NPanel verwalteten DNS-Records in Hetzner löschen
3. Wildcard-Zertifikat löschen
4. DNS-Records aus lokaler DB löschen (CASCADE)

### SubdomainObserver
**Location**: `app/Observers/SubdomainObserver.php`

**Events**:

#### `creating(Subdomain $subdomain)`
1. DNS-Validierung via `DnsValidationService`
2. **A-Record in Hetzner DNS erstellen**: `createRecord($zoneId, 'A', $subdomain_name, $server_ipv4)`
3. **Hetzner-Record-ID speichern** in `dns_records` Table
4. Document-Root erstellen via `DocumentRootService`
5. Placeholder index.html kopieren

#### `created(Subdomain $subdomain)`
1. Prüfen ob Domain Wildcard-SSL hat
2. Nginx-Config generieren (mit oder ohne Wildcard-SSL)
3. Symlink in sites-enabled erstellen
4. `sudo nginx -t` ausführen
5. Bei Erfolg: `sudo systemctl reload nginx`
6. Bei Fehler: 
   - A-Record in Hetzner DNS löschen
   - Config-Datei löschen
   - Document-Root löschen
   - Rollback
7. Log in `nginx_config_logs` und `hetzner_api_logs` erstellen

#### `updated(Subdomain $subdomain)`
1. DNS-Revalidierung
2. Wenn `subdomain_name` geändert:
   - **Alten A-Record in Hetzner DNS löschen**
   - **Neuen A-Record in Hetzner DNS erstellen**
   - **`dns_records` Table aktualisieren**
   - Document-Root umbenennen
3. Alte Config als Backup speichern (in `config_backup`)
4. Neue Config generieren
5. `sudo nginx -t` ausführen
6. Bei Erfolg: `sudo systemctl reload nginx` + PHP-FPM (wenn php_version geändert)
7. Bei Fehler: DNS-Änderungen rückgängig, Config-Rollback
8. Log erstellen

#### `deleted(Subdomain $subdomain)`
1. **A-Record in Hetzner DNS löschen** via `deleteRecord($recordId)`
2. Config-Backup erstellen
3. Nginx-Config löschen
4. Symlink aus sites-enabled entfernen
5. `sudo systemctl reload nginx`
6. Document-Root löschen (optional)
7. `dns_records` Eintrag löschen (CASCADE)
8. Log erstellen

## Controller

### DomainController
**Location**: `app/Http/Controllers/DomainController.php`

**Methods**:
```php
public function index() // Liste aller Domains
public function create() // Create-Form
public function store(Request $request) // Domain erstellen (Hetzner-Verifikation)
public function show(Domain $domain) // Domain-Details mit Subdomains + DNS-Records + Logs
public function update(Request $request, Domain $domain) // Domain aktualisieren
public function destroy(Domain $domain) // Domain löschen
public function syncDns(Domain $domain) // DNS-Records synchronisieren
public function enableWildcardSsl(Domain $domain) // Wildcard-SSL aktivieren
public function disableWildcardSsl(Domain $domain) // Wildcard-SSL deaktivieren
```

### SubdomainController
**Location**: `app/Http/Controllers/SubdomainController.php`

**Methods**:
```php
public function create(Domain $domain) // Create-Form
public function store(Request $request) // Subdomain erstellen (DNS + Nginx)
public function edit(Subdomain $subdomain) // Edit-Form
public function update(Request $request, Subdomain $subdomain) // Subdomain aktualisieren
public function destroy(Subdomain $subdomain) // Subdomain löschen (DNS + Nginx)
```

### DnsRecordController (neu)
**Location**: `app/Http/Controllers/DnsRecordController.php`

**Methods**:
```php
public function index(Domain $domain) // Alle DNS-Records einer Domain
public function store(Request $request, Domain $domain) // Manuelles Erstellen von DNS-Records
public function destroy(DnsRecord $dnsRecord) // DNS-Record löschen (nur nicht-managed)
```

### NginxConfigLogController
**Location**: `app/Http/Controllers/NginxConfigLogController.php`

**Methods**:
```php
public function index() // Alle Config-Logs mit Filter
public function show(NginxConfigLog $log) // Log-Details
```

### HetznerApiLogController (neu)
**Location**: `app/Http/Controllers/HetznerApiLogController.php`

**Methods**:
```php
public function index() // Alle Hetzner API Logs mit Filter
public function show(HetznerApiLog $log) // Log-Details mit Request/Response
```

## Routes

```php
// web.php
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Domains
    Route::resource('domains', DomainController::class);
    Route::post('domains/{domain}/sync-dns', [DomainController::class, 'syncDns'])->name('domains.sync-dns');
    Route::post('domains/{domain}/enable-wildcard-ssl', [DomainController::class, 'enableWildcardSsl'])->name('domains.enable-wildcard-ssl');
    Route::post('domains/{domain}/disable-wildcard-ssl', [DomainController::class, 'disableWildcardSsl'])->name('domains.disable-wildcard-ssl');
    
    // Subdomains
    Route::get('domains/{domain}/subdomains/create', [SubdomainController::class, 'create'])->name('subdomains.create');
    Route::post('subdomains', [SubdomainController::class, 'store'])->name('subdomains.store');
    Route::get('subdomains/{subdomain}/edit', [SubdomainController::class, 'edit'])->name('subdomains.edit');
    Route::put('subdomains/{subdomain}', [SubdomainController::class, 'update'])->name('subdomains.update');
    Route::delete('subdomains/{subdomain}', [SubdomainController::class, 'destroy'])->name('subdomains.destroy');
    
    // DNS Records
    Route::get('domains/{domain}/dns-records', [DnsRecordController::class, 'index'])->name('dns-records.index');
    Route::post('domains/{domain}/dns-records', [DnsRecordController::class, 'store'])->name('dns-records.store');
    Route::delete('dns-records/{dnsRecord}', [DnsRecordController::class, 'destroy'])->name('dns-records.destroy');
    
    // Logs
    Route::get('nginx-config-logs', [NginxConfigLogController::class, 'index'])->name('nginx-config-logs.index');
    Route::get('nginx-config-logs/{log}', [NginxConfigLogController::class, 'show'])->name('nginx-config-logs.show');
    Route::get('hetzner-api-logs', [HetznerApiLogController::class, 'index'])->name('hetzner-api-logs.index');
    Route::get('hetzner-api-logs/{log}', [HetznerApiLogController::class, 'show'])->name('hetzner-api-logs.show');
});
```

## Vue Components

### Domains/Index.vue
- Liste aller Domains mit Tabelle
- Spalten: Domain-Name, Hetzner-Status, Wildcard-SSL, DNS-Status, Subdomains-Count, Aktionen
- Filter: Hetzner-verified, Wildcard-SSL-enabled
- "Create Domain"-Button

### Domains/Create.vue
- Form: Domain-Name-Input
- Checkbox: "Wildcard-SSL aktivieren"
- Hinweis: "Domain muss in Hetzner DNS registriert sein"
- Server-IPv4-Anzeige
- Submit: POST /domains

### Domains/Show.vue
- Domain-Header mit Status-Badges
- Buttons: "DNS synchronisieren", "Wildcard-SSL aktivieren/deaktivieren"
- Tabs:
  - **Subdomains**: Liste mit Create-Button
  - **DNS-Records**: DnsRecordsList-Component
  - **Nginx-Logs**: ConfigLogsList-Component
  - **Hetzner-API-Logs**: HetznerApiLogsList-Component

### Subdomains/Create.vue
- Form:
  - Subdomain-Name (Text-Input)
  - PHP-Version (Dropdown: 7.4, 8.0, 8.1, 8.2, 8.3)
- Hinweis wenn Domain Wildcard-SSL hat: "SSL wird automatisch aktiviert"
- Document-Root wird automatisch generiert
- Submit: POST /subdomains

### Subdomains/Edit.vue
- Form (vorausgefüllt)
- Document-Root nicht editierbar
- PHP-Version änderbar
- Submit: PUT /subdomains/{id}

### DnsRecords/Index.vue
- Tabelle: Typ, Name, Wert, TTL, Verwaltet-von, Aktionen
- Badge "NPanel" für managed, "Importiert" für nicht-managed
- Nur nicht-managed Records können gelöscht werden
- Button: "Manuell DNS-Record hinzufügen"

### ConfigLogs/Index.vue
- Tabelle: Timestamp, Action, Type, Domain/Subdomain, Status, Error, Rollback-Icon
- Filter: Status, Type, Date-Range
- Pagination

### HetznerApiLogs/Index.vue (neu)
- Tabelle: Timestamp, Action, Endpoint, Status-Code, Details-Button
- Filter: Action, Status-Code, Date-Range
- Modal mit Request/Response bei Klick auf Details

## Nginx Templates

### 1. Catchall (catchall.blade.php)
```nginx
server {
    listen 80 default_server;
    server_name _;
    root /var/www/npanel/public/errors;
    index domain-not-found.html;
    location / {
        try_files $uri $uri/ /domain-not-found.html;
    }
}
```

### 2. Domain 404 (domain-404.blade.php)
```nginx
server {
    listen 80;
    server_name {{ $domain->domain_name }};
    root /var/www/npanel/public/errors;
    index no-subdomains.html;
    location / {
        try_files $uri $uri/ /no-subdomains.html;
    }
}
```

### 3. Subdomain ohne SSL (subdomain.blade.php)
```nginx
server {
    listen 80;
    server_name {{ $subdomain->subdomain_name }}.{{ $subdomain->domain->domain_name }};
    root {{ $subdomain->document_root }};
    index index.php index.html index.htm;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php{{ $subdomain->php_version }}-fpm-npanel.sock;
    }
}
```

### 4. Subdomain mit Wildcard-SSL (subdomain-wildcard-ssl.blade.php)
```nginx
server {
    listen 80;
    server_name {{ $subdomain->subdomain_name }}.{{ $subdomain->domain->domain_name }};
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name {{ $subdomain->subdomain_name }}.{{ $subdomain->domain->domain_name }};
    
    ssl_certificate {{ $subdomain->domain->wildcard_ssl_cert_path }};
    ssl_certificate_key {{ $subdomain->domain->wildcard_ssl_key_path }};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    root {{ $subdomain->document_root }};
    index index.php index.html index.htm;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php{{ $subdomain->php_version }}-fpm-npanel.sock;
    }
    
    add_header Strict-Transport-Security "max-age=31536000" always;
}
```

## Scheduler Tasks

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // DNS-Synchronisation mit Hetzner - alle 6 Stunden
    $schedule->command('hetzner:sync-dns')->everySixHours();
    
    // Wildcard-SSL-Renewal - täglich um 4 Uhr
    $schedule->command('ssl:renew-wildcard-certificates')->dailyAt('04:00');
    
    // Hetzner API Logs cleanup - wöchentlich
    $schedule->command('hetzner:cleanup-logs')->weekly();
}
```

## Artisan Commands

### system:install
- Erstellt `/etc/sudoers.d/npanel`
- Erstellt `/etc/nginx/sites-available/npanel/` Verzeichnis
- Erstellt `/var/www/vhosts/` Verzeichnis
- Generiert Shared PHP-FPM-Pools
- Erstellt Catchall-Nginx-Config
- Erstellt `/etc/letsencrypt/hetzner.ini` mit API-Token
- Testet alle Sudo-Berechtigungen
- Testet Hetzner DNS API Connection

### hetzner:sync-dns
- Lädt alle aktiven Domains
- Synchronisiert DNS-Records mit Hetzner DNS
- Erkennt manuelle Änderungen
- Updated lokale Datenbank

### hetzner:test-connection
- Testet Hetzner DNS API Token
- Zeigt verfügbare Zones an

### ssl:renew-wildcard-certificates
- Lädt alle Domains mit Wildcard-SSL
- Führt `certbot renew --dns-hetzner` aus
- Loggt Ergebnis
- Reload Nginx wenn erneuert

### hetzner:cleanup-logs
- Löscht `hetzner_api_logs` älter als 90 Tage (außer Errors)

## Sudoers Configuration

**File**: `/etc/sudoers.d/npanel`

```bash
# NPanel - Domain Management System
www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx -t
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload php7.4-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.0-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.1-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.2-fpm
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.3-fpm
www-data ALL=(ALL) NOPASSWD: /bin/mkdir -p /var/www/vhosts/*
www-data ALL=(ALL) NOPASSWD: /bin/chown -R www-data\:www-data /var/www/vhosts/*
www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot certonly --dns-hetzner*
www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot renew
www-data ALL=(ALL) NOPASSWD: /bin/ln -s /etc/nginx/sites-available/* /etc/nginx/sites-enabled/*
www-data ALL=(ALL) NOPASSWD: /bin/rm /etc/nginx/sites-enabled/*
www-data ALL=(ALL) NOPASSWD: /bin/chmod 600 /etc/letsencrypt/hetzner.ini
```

## Installation Steps

### 1. System-Voraussetzungen
```bash
# PHP 7.4 - 8.3 mit FPM
apt install php7.4-fpm php8.0-fpm php8.1-fpm php8.2-fpm php8.3-fpm

# PHP Extensions
apt install php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip

# Nginx
apt install nginx

# Certbot + DNS-Hetzner-Plugin
apt install certbot python3-pip
pip3 install certbot-dns-hetzner

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Node.js & npm
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install nodejs

# MySQL
apt install mysql-server
```

### 2. Laravel-Installation in temp-Verzeichnis
```bash
cd /tmp
composer create-project laravel/laravel npanel-temp
mv npanel-temp/* /var/www/npanel/
mv npanel-temp/.* /var/www/npanel/ 2>/dev/null || true
rm -rf npanel-temp
cd /var/www/npanel
```

### 3. Breeze mit Inertia
```bash
composer require laravel/breeze --dev
php artisan breeze:install vue
npm install
npm run build
```

### 4. Guzzle installieren
```bash
composer require guzzlehttp/guzzle
```

### 5. .env konfigurieren
```bash
cp .env.example .env
php artisan key:generate

# .env anpassen:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=npanel
# DB_USERNAME=npanel_user
# DB_PASSWORD=secure_password
# 
# HETZNER_DNS_API_TOKEN=your_token_here
```

### 6. Datenbank erstellen
```bash
mysql -u root -p
CREATE DATABASE npanel;
CREATE USER 'npanel_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON npanel.* TO 'npanel_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 7. Migrations ausführen
```bash
php artisan migrate
```

### 8. System-Installation
```bash
php artisan system:install
```

### 9. Berechtigungen setzen
```bash
chown -R www-data:www-data /var/www/npanel
chmod -R 755 /var/www/npanel
chmod -R 775 /var/www/npanel/storage
chmod -R 775 /var/www/npanel/bootstrap/cache
```

### 10. Nginx NPanel-App-Config
```bash
# /etc/nginx/sites-available/npanel-app.conf
server {
    listen 80;
    server_name your-panel-domain.com;
    root /var/www/npanel/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}

ln -s /etc/nginx/sites-available/npanel-app.conf /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

### 11. Scheduler aktivieren
```bash
crontab -u www-data -e
# Hinzufügen:
* * * * * cd /var/www/npanel && php artisan schedule:run >> /dev/null 2>&1
```

### 12. Hetzner DNS API testen
```bash
php artisan hetzner:test-connection
```

## Deployment Checklist

- [ ] System-Requirements installiert (PHP, Nginx, Certbot, etc.)
- [ ] Laravel installiert und konfiguriert
- [ ] Breeze mit Inertia installiert
- [ ] Guzzle installiert
- [ ] Hetzner DNS API Token erstellt und in .env
- [ ] Datenbank erstellt und Migrations ausgeführt
- [ ] Alle Service-Klassen implementiert
- [ ] Model-Observer registriert (Domain + Subdomain)
- [ ] Controller implementiert (Domain, Subdomain, DnsRecord, Logs)
- [ ] Routes definiert
- [ ] Vue-Components erstellt
- [ ] Nginx-Templates erstellt (inkl. Wildcard-SSL)
- [ ] PHP-FPM-Shared-Pools konfiguriert
- [ ] Sudoers-Datei erstellt mit allen Berechtigungen
- [ ] Catchall-Config aktiviert
- [ ] Error-Pages erstellt (domain-not-found, no-subdomains)
- [ ] Placeholder-Template erstellt (index.html)
- [ ] `/etc/letsencrypt/hetzner.ini` erstellt
- [ ] `certbot-dns-hetzner` installiert
- [ ] Scheduler-Commands implementiert
- [ ] Crontab konfiguriert
- [ ] System-Install-Command getestet
- [ ] Berechtigungen geprüft
- [ ] Hetzner DNS API Connection getestet
- [ ] Wildcard-SSL Dry-Run erfolgreich
- [ ] Tests geschrieben und durchgeführt
- [ ] Dokumentation aktualisiert
- [ ] Git-Repository gepusht

## Implementation Tasks (Start)

1. **Laravel-Projekt Setup**
   - Laravel in temp installieren und nach /var/www/npanel verschieben
   - Breeze mit Inertia installieren
   - Guzzle installieren
   - .env konfigurieren

2. **Datenbank-Migrations erstellen**
   - domains, subdomains, dns_records, nginx_config_logs, hetzner_api_logs

3. **Models erstellen**
   - Domain, Subdomain, DnsRecord, NginxConfigLog, HetznerApiLog
   - Relationships definieren

4. **Service-Klassen implementieren**
   - HetznerDnsService
   - WildcardSslService
   - DnsValidationService
   - NginxService
   - PhpFpmService
   - DocumentRootService

5. **Observer implementieren**
   - DomainObserver
   - SubdomainObserver

6. **Controller implementieren**
   - DomainController
   - SubdomainController
   - DnsRecordController
   - NginxConfigLogController
   - HetznerApiLogController

7. **Routes definieren**
   - web.php mit allen Routes

8. **Vue-Components erstellen**
   - Domains, Subdomains, DnsRecords, ConfigLogs

9. **Nginx-Templates erstellen**
   - catchall, domain-404, subdomain, subdomain-wildcard-ssl

10. **Artisan-Commands implementieren**
    - system:install
    - hetzner:sync-dns
    - hetzner:test-connection
    - ssl:renew-wildcard-certificates
    - hetzner:cleanup-logs

11. **Testing & Deployment**
    - Unit-Tests
    - Feature-Tests
    - Manual-Tests

---

**Version**: 2.0.0 (Master Plan with Hetzner DNS)  
**Last Updated**: 2025-11-24
