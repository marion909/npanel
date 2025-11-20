# nPanel - Web Hosting Control Panel

## Architecture Overview

nPanel is a modern web hosting control panel similar to aaPanel/cPanel, built with Laravel 11 + Vue.js 3 + Inertia.js. It manages domains, subdomains, Nginx virtual hosts, multi-version PHP-FPM pools, and automated SSL certificates via Let's Encrypt.

### Tech Stack
- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Vue.js 3 + Inertia.js + Tailwind CSS
- **Database**: SQLite/MySQL/PostgreSQL (configurable)
- **Queue**: Redis for async job processing
- **Web Server**: Nginx (managed by panel)
- **PHP**: Multi-version support (7.4 - 8.3)
- **SSL**: acme.sh for Let's Encrypt automation

### Key Architectural Patterns

**Service Layer Pattern**: Business logic lives in `App\Services\` (DomainService, NginxService, PhpFpmService, SSLService). Controllers remain thin and delegate to services.

**Queue-Based Operations**: Long-running tasks (domain creation, SSL issuance, Nginx reloads) use Laravel Queue jobs (`App\Jobs\`) to prevent timeout issues and enable background processing.

**Configuration Templating**: Nginx vhosts and PHP-FPM pools are generated from Blade templates in `resources/templates/` with variables for domain-specific values.

**Safe Reload Pattern**: All service reloads (Nginx, PHP-FPM) use a test-before-reload approach:
1. Generate configuration
2. Test with `nginx -t` or `php-fpm -t`
3. Backup current config
4. Apply new config
5. Reload service
6. Rollback on failure

## Directory Structure

```
app/
├── Http/Controllers/       # Thin controllers (DomainController, SubdomainController, etc.)
├── Models/                 # Eloquent models (Domain, Subdomain, SslCertificate, PhpFpmPool)
├── Services/               # Business logic (DomainService, NginxService, PhpFpmService, SSLService)
├── Jobs/                   # Queue jobs (CreateDomainJob, IssueSslCertificateJob, RenewSslJob)
└── Middleware/             # Auth and validation middleware

database/migrations/        # Schema for domains, subdomains, ssl_certificates, php_fpm_pools, nginx_configs

resources/
├── js/
│   ├── Pages/             # Vue.js page components (Dashboard.vue, Domains/Index.vue, etc.)
│   └── Components/        # Reusable Vue components
├── templates/             # Blade templates for Nginx/PHP-FPM configs
│   ├── nginx/
│   │   ├── domain.conf.blade.php      # Main domain vhost
│   │   └── subdomain.conf.blade.php   # Subdomain vhost
│   └── php-fpm/
│       └── pool.conf.blade.php        # PHP-FPM pool config
└── css/

config/npanel.php          # Custom panel configuration (paths, defaults)
```

## Core Workflows

### Domain Creation
1. User submits domain form (name, document_root optional)
2. `DomainController@store` validates input
3. Dispatches `CreateDomainJob` to queue
4. Job creates:
   - Database record with status='pending'
   - Directory structure: `/home/{user}/domains/{domain}/public_html`, `logs/`, `tmp/`, `subdomains/`
   - Two default subdomains: 'www' and '@' (root)
   - Nginx vhost from template
   - PHP-FPM pool for latest PHP version
   - Symlink to `sites-enabled/`
5. Tests and reloads Nginx + PHP-FPM
6. Updates status='active'
7. Triggers SSL certificate issuance (async)

### Subdomain Management
- Subdomains belong to parent domain via `parent_domain_id`
- Can have custom `document_root` (default: `/home/{user}/domains/{parent}/subdomains/{sub}`)
- Can use different PHP version than parent domain
- Generate separate Nginx vhost but share SSL certificate (wildcard) or get own cert

### PHP-FPM Multi-Version Support
- System has PHP 7.4, 8.0, 8.1, 8.2, 8.3 installed
- Each domain/subdomain selects PHP version
- `PhpFpmService` generates dedicated pool config per domain
- Pool runs as isolated Unix user (future: per-domain users)
- Socket path: `/var/run/php/php{version}-fpm-{domain}.sock`
- Configuration includes resource limits (memory_limit, max_execution_time, open_basedir)

### SSL Certificate Automation
- Uses acme.sh (installed at `/root/.acme.sh/`)
- Issued via HTTP-01 challenge (webroot validation)
- Covers domain + www + @ subdomains
- `SSLService@issueCertificate()` handles:
  - Domain DNS verification (optional)
  - ACME challenge directory creation
  - Certificate issuance
  - Installation to `/etc/letsencrypt/live/{domain}/`
  - Nginx config update with SSL paths
  - Database record with expiry tracking
- Auto-renewal via daily cron job + post-renewal hooks

### Nginx Configuration Generation
- Templates in `resources/templates/nginx/`
- Variables: domain name, document_root, php_version, php_fpm_socket, ssl_cert_path, ssl_key_path
- Generated configs written to `/etc/nginx/sites-available/{domain}.conf`
- Symlinked to `/etc/nginx/sites-enabled/`
- Always includes security headers snippet
- HTTP → HTTPS redirect when SSL enabled

## Database Schema

### domains
- `id`, `user_id`, `domain_name` (unique)
- `document_root`, `nginx_config_path`
- `php_version` (default: '8.3'), `php_fpm_pool`
- `ssl_enabled` (boolean), `ssl_cert_path`, `ssl_key_path`, `ssl_expiry_date`
- `status` (pending|active|suspended|deleted)
- `created_at`, `updated_at`

### subdomains
- `id`, `parent_domain_id` (FK to domains)
- `subdomain_name`, `document_root`, `nginx_config_path`
- `php_version`, `php_fpm_pool`
- `ssl_enabled`
- `created_at`, `updated_at`
- UNIQUE(`subdomain_name`, `parent_domain_id`)

### ssl_certificates
- `id`, `domain_id` (FK)
- `certificate_path`, `private_key_path`, `chain_path`
- `provider` (letsencrypt|manual|self-signed)
- `issue_date`, `expiry_date`, `auto_renew` (boolean)
- `last_renewal_attempt`

### php_fpm_pools
- `id`, `domain_id` (FK)
- `pool_name` (unique), `php_version`, `socket_path`
- `pm_mode`, `pm_max_children`, `pm_start_servers`, etc.
- `memory_limit`, `max_execution_time`

## Configuration Files

### config/npanel.php
```php
return [
    'base_path' => env('NPANEL_BASE_PATH', '/home'),
    'nginx_sites_available' => '/etc/nginx/sites-available',
    'nginx_sites_enabled' => '/etc/nginx/sites-enabled',
    'php_versions' => ['7.4', '8.0', '8.1', '8.2', '8.3'],
    'default_php_version' => '8.3',
    'acme_sh_path' => '/root/.acme.sh/acme.sh',
    'ssl_provider' => 'letsencrypt',
];
```

## Important Commands

```bash
# Install dependencies
composer install
npm install

# Run migrations
php artisan migrate

# Seed database (if applicable)
php artisan db:seed

# Start development server
php artisan serve

# Start Vite dev server (assets)
npm run dev

# Queue worker (required for async jobs)
php artisan queue:work --tries=3

# Run tests
php artisan test

# Publish Inertia middleware
php artisan inertia:middleware

# Create migration
php artisan make:migration create_domains_table

# Create model
php artisan make:model Domain -mcr  # with migration, controller, resource

# Create service
php artisan make:service DomainService

# Create job
php artisan make:job CreateDomainJob
```

## Conventions

### Naming
- **Models**: Singular PascalCase (Domain, SslCertificate)
- **Controllers**: PascalCase + 'Controller' suffix (DomainController)
- **Services**: PascalCase + 'Service' suffix (NginxService)
- **Jobs**: PascalCase + 'Job' suffix (CreateDomainJob)
- **Migrations**: snake_case with timestamp prefix
- **Routes**: plural kebab-case (`/api/domains`, `/api/domains/{id}/subdomains`)

### Code Style
- Follow PSR-12 coding standards
- Use Laravel Pint for automatic formatting: `./vendor/bin/pint`
- Type hints for all method parameters and return types
- Dependency injection in constructors

### API Responses
```php
// Success
return response()->json([
    'message' => 'Domain created successfully',
    'data' => $domain
], 201);

// Validation Error
return response()->json([
    'message' => 'Validation failed',
    'errors' => $validator->errors()
], 422);

// Server Error
return response()->json([
    'message' => 'Failed to create domain',
    'error' => $exception->getMessage()
], 500);
```

### Service Methods Pattern
```php
class DomainService {
    public function createDomain(array $data): Domain {
        // 1. Validate and prepare data
        // 2. Create database record
        // 3. Dispatch queue job for heavy operations
        // 4. Return domain model
    }
}
```

### Queue Job Pattern
```php
class CreateDomainJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function handle() {
        try {
            // 1. Perform operation
            // 2. Update database status
            // 3. Log success
        } catch (\Exception $e) {
            // Log error, possibly retry
            $this->fail($e);
        }
    }
}
```

## Security Considerations

- **User Isolation**: Future versions will use separate Unix users per domain
- **open_basedir**: PHP-FPM pools restrict file access to domain directory + /tmp
- **Nginx Security Headers**: X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, HSTS
- **Disable Dangerous PHP Functions**: exec, passthru, shell_exec, system in PHP-FPM pools
- **File Permissions**: 
  - Domain directories: 755 (user:user)
  - Web server access via group permissions
  - PHP-FPM runs as domain user

## Testing Strategy

- **Feature Tests**: Test API endpoints and domain creation workflows
- **Unit Tests**: Test service logic in isolation
- **Integration Tests**: Test Nginx config generation and validation
- **Manual Testing**: Always verify Nginx/PHP-FPM configs on real system before production

## External Dependencies

- **acme.sh**: Must be installed at `/root/.acme.sh/`
- **Multiple PHP versions**: Install via apt: `php7.4-fpm php8.0-fpm php8.1-fpm php8.2-fpm php8.3-fpm`
- **Nginx**: Installed and running with sites-available/sites-enabled pattern
- **Redis**: Required for queue functionality
- **Sudo permissions**: Laravel process needs sudo access for nginx/php-fpm reload (or run as root - security concern)

## Troubleshooting

### Common Issues

**Queue jobs not processing**: Run `php artisan queue:work` or configure supervisor for production

**Nginx reload fails**: Check `nginx -t` output, review generated config in `/etc/nginx/sites-available/`

**PHP-FPM pool not starting**: Check `/var/log/php{version}-fpm.log`, verify socket path and permissions

**SSL issuance fails**: Verify domain DNS points to server, check acme.sh logs, ensure port 80 accessible

**Permissions errors**: Ensure Laravel has write access to storage/, bootstrap/cache/, and domain directories

## Development Workflow

1. **Create migration** for database changes
2. **Run migration** to apply schema
3. **Create/update model** with relationships and casts
4. **Create service** for business logic
5. **Create controller** to handle HTTP requests
6. **Create job** for async operations
7. **Define routes** in `routes/api.php` or `routes/web.php`
8. **Create Vue component** for frontend
9. **Test manually** via Postman or browser
10. **Write automated tests**

## When Making Changes

- **Database changes**: Always create migration, never modify database directly
- **Config changes**: Add to `config/npanel.php`, use `config()` helper to access
- **New service operations**: Test Nginx/PHP-FPM config generation thoroughly
- **Queue jobs**: Always include retry logic and error handling
- **Frontend**: Run `npm run dev` during development, `npm run build` for production

## Useful Resources

- Laravel 11 Docs: https://laravel.com/docs/11.x
- Inertia.js Docs: https://inertiajs.com/
- Vue.js 3 Docs: https://vuejs.org/
- Nginx Config Docs: https://nginx.org/en/docs/
- acme.sh Docs: https://github.com/acmesh-official/acme.sh
