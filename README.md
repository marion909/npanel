# nPanel - Modern Web Hosting Control Panel

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11">
  <img src="https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white" alt="Vue.js 3">
  <img src="https://img.shields.io/badge/Inertia.js-2-9553E9?style=for-the-badge" alt="Inertia.js">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License">
</p>

<p align="center">
  <strong>🌐 <a href="https://npanel.at">npanel.at</a> | 📧 <a href="https://webmail.npanel.at">Webmail</a> | 📚 <a href="#-quick-installation">Installation</a></strong>
</p>

A modern, powerful web hosting control panel similar to aaPanel/cPanel, built with cutting-edge technologies. Manage domains, subdomains, Nginx virtual hosts, multi-version PHP-FPM pools, mail servers, databases, and automated SSL certificates with ease.

## ✨ Features

### 🌐 Domain Management
- **Full CRUD Operations** - Create, read, update, and delete domains
- **Subdomain Support** - Unlimited subdomains per domain with individual configurations
- **Automatic Nginx Configuration** - Virtual hosts generated from templates
- **Bulk Operations** - Manage multiple domains efficiently

### 📧 Mail Server (Full Stack)
- **Postfix SMTP** - Reliable mail transfer agent on ports 25/587
- **Dovecot IMAP** - Secure mail access on port 143 with SQL authentication
- **Roundcube Webmail** - Modern web interface at webmail.npanel.at
- **Virtual Mailboxes** - MySQL-backed mailboxes with per-domain management
- **SASL Authentication** - Secure SMTP submission via Dovecot
- **Sender Login Maps** - Anti-spoofing protection
- **Automated Setup** - Complete mail stack configured by installer

### 🗄️ Database Management
- **MySQL/MariaDB** - Full database server support
- **phpMyAdmin Integration** - Web-based database management
- **Per-Domain Databases** - Isolated database access for each domain
- **Automated Backups** - Scheduled database backups

### ⚙️ PHP-FPM Multi-Version
- **PHP 7.4 to 8.3** - Switch between PHP versions per domain/subdomain
- **Isolated Pools** - Each domain runs in its own PHP-FPM pool
- **Resource Management** - Configure memory limits, max execution time, process managers
- **Security** - Built-in `open_basedir` restrictions and disabled dangerous functions

### 🔒 SSL Automation
- **Let's Encrypt Integration** - Automated SSL certificate issuance via acme.sh
- **HTTP-01 Challenge** - Webroot validation for certificate generation
- **Auto-Renewal** - Certificates renew automatically before expiration
- **Wildcard Support** - Cover domain + www + subdomains with single certificate

### 🚀 Nginx Management
- **Auto-Configuration** - Generate virtual host configs from templates
- **Safe Reload Pattern** - Test configs before applying (rollback on failure)
- **Security Headers** - Built-in XSS, HSTS, and frame protection
- **Custom Rewrites** - Support for popular CMS platforms

### 📊 Background Processing
- **Queue System** - Long-running tasks handled asynchronously via Redis
- **Domain Activation** - Non-blocking domain setup and provisioning
- **SSL Issuance** - Background certificate generation and installation
- **Scheduled Jobs** - Automated SSL renewal, cleanup, and maintenance

### 🛡️ Security
- **Sanctum Authentication** - Token-based API security
- **Policy-Based Authorization** - Users can only access their own resources
- **Secure Defaults** - Disable dangerous PHP functions, enforce HTTPS
- **Audit Logging** - Track all configuration changes

## 🛠️ Tech Stack

| Component | Technology |
|-----------|-----------|
| **Backend Framework** | Laravel 11 (PHP 8.2+) |
| **Frontend Framework** | Vue.js 3 with Composition API |
| **SPA Architecture** | Inertia.js (server-side routing) |
| **Styling** | Tailwind CSS 3 |
| **Database** | SQLite / MySQL / PostgreSQL |
| **Queue Backend** | Redis |
| **Web Server** | Nginx (managed by panel) |
| **PHP Runtime** | Multi-version PHP-FPM (7.4-8.3) |
| **SSL Provider** | Let's Encrypt via acme.sh |
| **Mail Server (SMTP)** | Postfix 3.x |
| **Mail Server (IMAP)** | Dovecot 2.4+ |
| **Webmail** | Roundcube 1.6+ |
| **Database Management** | phpMyAdmin |
| **Authentication** | Laravel Sanctum |

## 📋 Requirements

### System Requirements
- **OS**: Ubuntu 20.04+ or Debian 10+ (64-bit)
- **RAM**: Minimum 2GB, recommended 4GB+
- **Disk**: 20GB+ free space
- **CPU**: 2+ cores recommended

### Software Requirements
- PHP 8.2 or higher
- Nginx (installed and configured)
- Redis Server
- MySQL 8.0+ / PostgreSQL 13+ / SQLite 3
- Node.js 18+ and npm
- Composer 2.x
- Git

## 🚀 Quick Installation

### Automated Installation (Recommended)

```bash
# Clone repository
git clone https://github.com/marion909/npanel.git
cd npanel

# Run automated installer
chmod +x install.sh
sudo ./install.sh
```

The installer will:
- ✅ Install all system dependencies (Nginx, Redis, MySQL, PHP versions)
- ✅ Set up PHP-FPM pools for PHP 7.4, 8.0, 8.1, 8.2, 8.3
- ✅ Install and configure acme.sh for SSL
- ✅ Install and configure mail server (Postfix + Dovecot + Roundcube)
- ✅ Set up phpMyAdmin for database management
- ✅ Create database and user with secure password
- ✅ Install PHP and Node.js dependencies
- ✅ Configure Nginx virtual host for the panel
- ✅ Set up Supervisor for queue workers
- ✅ Configure cron jobs for scheduled tasks
- ✅ Create admin user interactively
- ✅ Optionally install SSL certificate for panel itself

### Manual Installation

See [INSTALLATION.md](INSTALLATION.md) for detailed step-by-step instructions.

## 📖 Usage

### Access the Panel

After installation, access the panel at:
```
https://your-panel-domain.com
```

Login with the admin credentials you created during installation.

### Create Your First Domain

1. **Navigate to Dashboard** - Click "Domains" in the navigation
2. **Add New Domain** - Click "Create Domain" button
3. **Configure Domain**:
   - Enter domain name (e.g., `example.com`)
   - Select PHP version (default: 8.3)
   - Set document root (optional)
4. **Submit** - Domain is created in background
5. **Wait for Activation** - Check status (pending → active)
6. **SSL Certificate** - Automatically issued after domain activation

### Manage Subdomains

1. **Select Domain** - Click on domain in list
2. **Subdomains Tab** - View existing subdomains (www, @)
3. **Add Subdomain** - Enter subdomain name (e.g., `blog`)
4. **Configure** - Choose PHP version, set custom document root
5. **SSL Coverage** - Covered by parent domain's wildcard certificate

### Mail Server Management

Access webmail at **[webmail.npanel.at](https://webmail.npanel.at)**

#### Creating Mailboxes

1. **Navigate to Domains** - Select your domain
2. **Mailboxes Tab** - View existing mailboxes
3. **Add Mailbox** - Enter email address and password
4. **Configure Quota** - Set storage limits per mailbox

#### Email Client Configuration

**IMAP Settings:**
- Server: `mail.yourdomain.com`
- Port: `143` (STARTTLS) or `993` (SSL/TLS)
- Authentication: Email address and password

**SMTP Settings:**
- Server: `mail.yourdomain.com`
- Port: `587` (STARTTLS - recommended) or `25`
- Authentication: Email address and password

#### Features
- Virtual mailboxes with MySQL backend
- SASL authentication via Dovecot
- Sender login maps for anti-spoofing
- Automated mail server configuration
- IPv4 preference for reliable delivery

### API Usage

nPanel provides a RESTful API with Sanctum authentication:

```bash
# Login and get token
curl -X POST https://panel.example.com/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"your_password"}'

# Create domain via API
curl -X POST https://panel.example.com/api/domains \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"domain_name":"example.com","php_version":"8.3"}'

# List domains
curl https://panel.example.com/api/domains \
  -H "Authorization: Bearer YOUR_TOKEN"
```

See [API Documentation](docs/API.md) for complete endpoint reference.

## 🏗️ Architecture

### Service Layer Pattern
Business logic is encapsulated in service classes:
- `DomainService` - Domain CRUD and provisioning
- `NginxService` - Virtual host generation and safe reloads
- `PhpFpmService` - Multi-version pool management
- `SSLService` - Let's Encrypt automation
- `SubdomainService` - Subdomain operations
- `MailService` - Mailbox and alias management
- `PostfixService` - SMTP server configuration
- `DovecotService` - IMAP server and authentication setup
- `DatabaseService` - MySQL database and user management
- `PhpMyAdminService` - phpMyAdmin integration

### Queue-Based Operations
Long-running tasks are dispatched to background jobs:
- `ActivateDomainJob` - Domain directory creation, config generation, service reloads
- `IssueSslCertificateJob` - Certificate issuance and installation
- `RenewSslCertificatesJob` - Automatic renewal for expiring certificates
- `InstallMailServerJob` - Complete mail server stack setup
- `DeleteDomainJob` - Clean removal of domains and associated resources
- `ReloadServicesJob` - Safe service reload after configuration changes

### Database Schema
```
users
  - id, name, email, password

domains
  - id, user_id, domain_name
  - document_root, nginx_config_path
  - php_version, php_fpm_pool
  - ssl_enabled, ssl_cert_path, ssl_expiry_date
  - status (pending|active|suspended|deleted)

subdomains
  - id, parent_domain_id
  - subdomain_name, document_root
  - php_version, php_fpm_pool

ssl_certificates
  - id, domain_id
  - certificate_path, private_key_path
  - provider, issue_date, expiry_date

php_fpm_pools
  - id, domain_id
  - pool_name, php_version, socket_path
  - pm_mode, pm_max_children, memory_limit

mailboxes
  - id, domain_id, email, password
  - quota, status

mail_aliases
  - id, domain_id, source, destination

databases
  - id, user_id, database_name
  - username, password, host
```

## 🔧 Configuration

### Panel Configuration

Edit `config/npanel.php`:

```php
return [
    'base_path' => '/home',                    // Base directory for domains
    'nginx_sites_available' => '/etc/nginx/sites-available',
    'nginx_sites_enabled' => '/etc/nginx/sites-enabled',
    'php_versions' => ['7.4', '8.0', '8.1', '8.2', '8.3'],
    'default_php_version' => '8.3',
    'acme_sh_path' => '/root/.acme.sh/acme.sh',
    'ssl_provider' => 'letsencrypt',
];
```

### Environment Variables

Key `.env` settings:

```env
APP_NAME=nPanel
APP_URL=https://panel.yourdomain.com

DB_CONNECTION=mysql
DB_DATABASE=npanel
DB_USERNAME=npanel_user
DB_PASSWORD=secure_password

QUEUE_CONNECTION=redis

NPANEL_BASE_PATH=/home
NPANEL_DEFAULT_PHP_VERSION=8.3
```

## 🧪 Development

### Setup Development Environment

```bash
# Clone repository
git clone https://github.com/marion909/npanel.git
cd npanel

# Install dependencies
composer install
npm install

# Copy environment file
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start queue worker
php artisan queue:work &

# Start development servers
npm run dev          # Vite dev server (Terminal 1)
php artisan serve    # Laravel dev server (Terminal 2)
```

Access at `http://localhost:8000`

### Run Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter DomainTest

# Run with coverage
php artisan test --coverage
```

### Code Style

```bash
# Format code with Laravel Pint
./vendor/bin/pint

# Check for issues
./vendor/bin/pint --test
```

## 📚 Documentation

- [Installation Guide](INSTALLATION.md) - Detailed installation instructions
- [API Reference](docs/API.md) - Complete API documentation
- [Architecture Guide](.github/copilot-instructions.md) - System architecture and patterns
- [Contributing](CONTRIBUTING.md) - Contribution guidelines
- [Changelog](CHANGELOG.md) - Version history and changes

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. **Fork the repository**
2. **Create feature branch** (`git checkout -b feature/amazing-feature`)
3. **Commit changes** (`git commit -m 'Add amazing feature'`)
4. **Push to branch** (`git push origin feature/amazing-feature`)
5. **Open Pull Request**

Please ensure:
- Code follows PSR-12 standards
- All tests pass (`php artisan test`)
- New features include tests
- Documentation is updated

## 🐛 Troubleshooting

### Queue Not Processing?
```bash
sudo supervisorctl restart npanel-worker:*
```

### Nginx Config Test Fails?
```bash
sudo nginx -t
# Fix any syntax errors shown, then:
sudo systemctl reload nginx
```

### Mail Server Issues?

**Check Services:**
```bash
sudo systemctl status postfix dovecot
journalctl -u postfix -f
journalctl -u dovecot -f
```

**Test IMAP Authentication:**
```bash
doveadm auth test user@domain.com password
```

**Check Mail Queue:**
```bash
postqueue -p
```

**Reset Mailbox Password:**
```bash
./reset-mailbox-password.sh user@domain.com newpassword
```

**Test SMTP Sending:**
```bash
telnet localhost 587
EHLO localhost
AUTH LOGIN
# Use base64 encoded credentials
```

### Permission Errors?
```bash
sudo chown -R www-data:www-data /var/www/npanel/storage
sudo chmod -R 775 /var/www/npanel/storage
```

### PHP-FPM Pool Not Starting?
```bash
sudo systemctl status php8.3-fpm
sudo tail -f /var/log/php8.3-fpm.log
```

### SSL Issuance Fails?
- Verify domain DNS points to server
- Ensure port 80 is accessible
- Check acme.sh logs: `~/.acme.sh/acme.sh --log`

## 🌟 Demo

Visit **[npanel.at](https://npanel.at)** to see nPanel in action!

**Webmail Access:** [webmail.npanel.at](https://webmail.npanel.at)

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## 🙏 Acknowledgments

- Built with [Laravel](https://laravel.com) - The PHP Framework for Web Artisans
- Powered by [Vue.js](https://vuejs.org) - The Progressive JavaScript Framework
- Enhanced with [Inertia.js](https://inertiajs.com) - The Modern Monolith
- Styled with [Tailwind CSS](https://tailwindcss.com) - Rapidly build modern websites
- SSL via [acme.sh](https://github.com/acmesh-official/acme.sh) - Pure Unix shell script implementing ACME client protocol

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/marion909/npanel/issues)
- **Discussions**: [GitHub Discussions](https://github.com/marion909/npanel/discussions)
- **Documentation**: [Wiki](https://github.com/marion909/npanel/wiki)

## 🗺️ Roadmap

- [ ] Multi-user support with tenant isolation
- [ ] FTP/SFTP user management
- [ ] Database management (MySQL/PostgreSQL)
- [ ] Backup and restore functionality
- [ ] Email account management
- [ ] Resource usage monitoring and alerts
- [ ] Docker container support
- [ ] Two-factor authentication
- [ ] Mobile-responsive dashboard improvements
- [ ] Dark mode theme

---

<p align="center">Made with ❤️ by <a href="https://github.com/marion909">marion909</a></p>
