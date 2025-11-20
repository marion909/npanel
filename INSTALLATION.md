# nPanel - Installation & Setup Guide

## 🚀 Quick Start

This guide will help you set up nPanel on your server.

## Prerequisites

- Ubuntu/Debian server (20.04+ recommended)
- Root or sudo access
- PHP 8.2+
- MySQL/PostgreSQL/SQLite
- Redis
- Node.js 18+
- Composer 2.x

## Step-by-Step Installation

### 1. System Update
```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Install Required Software

**Install Nginx**:
```bash
sudo apt install nginx -y
sudo systemctl enable nginx
sudo systemctl start nginx
```

**Install Redis**:
```bash
sudo apt install redis-server -y
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

**Install MySQL** (or PostgreSQL):
```bash
sudo apt install mysql-server -y
sudo mysql_secure_installation
```

**Install PHP 8.3 and extensions**:
```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install php8.3-{cli,fpm,mysql,mbstring,xml,curl,gd,zip,redis} -y
```

**Install Composer**:
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

**Install Node.js**:
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install nodejs -y
```

### 3. Install Multiple PHP Versions

```bash
# Install PHP 7.4 through 8.3
for version in 7.4 8.0 8.1 8.2 8.3; do
    sudo apt install php${version}-{cli,fpm,mysql,mbstring,xml,curl,gd,zip,redis} -y
    sudo systemctl enable php${version}-fpm
    sudo systemctl start php${version}-fpm
done
```

### 4. Install acme.sh for SSL

```bash
curl https://get.acme.sh | sh -s email=admin@yourdomain.com
source ~/.bashrc
```

### 5. Clone nPanel

```bash
cd /var/www
sudo git clone https://github.com/marion909/npanel.git
cd npanel
```

### 6. Install Dependencies

```bash
# PHP dependencies
composer install --no-dev --optimize-autoloader

# Node.js dependencies
npm install
npm run build
```

### 7. Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate
```

**Edit `.env`**:
```bash
nano .env
```

Configure:
```env
APP_NAME=nPanel
APP_ENV=production
APP_DEBUG=false
APP_URL=https://panel.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=npanel
DB_USERNAME=npanel_user
DB_PASSWORD=your_secure_password

REDIS_HOST=127.0.0.1
QUEUE_CONNECTION=redis

NPANEL_BASE_PATH=/home
NPANEL_DEFAULT_USER=www-data
NPANEL_DEFAULT_PHP_VERSION=8.3
```

### 8. Create Database

```bash
sudo mysql
```

```sql
CREATE DATABASE npanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'npanel_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON npanel.* TO 'npanel_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 9. Run Migrations

```bash
php artisan migrate --force
```

### 10. Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/npanel
sudo chmod -R 755 /var/www/npanel
sudo chmod -R 775 /var/www/npanel/storage
sudo chmod -R 775 /var/www/npanel/bootstrap/cache
```

### 11. Configure Nginx for Panel

Create `/etc/nginx/sites-available/npanel.conf`:

```nginx
server {
    listen 80;
    server_name panel.yourdomain.com;
    root /var/www/npanel/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/npanel.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 12. Configure Supervisor for Queue Worker

Install Supervisor:
```bash
sudo apt install supervisor -y
```

Create `/etc/supervisor/conf.d/npanel-worker.conf`:

```ini
[program:npanel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/npanel/artisan queue:work --tries=3 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/npanel/storage/logs/worker.log
stopwaitsecs=3600
```

Start worker:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start npanel-worker:*
```

### 13. Configure Cron for Scheduled Tasks

```bash
sudo crontab -e
```

Add:
```cron
* * * * * cd /var/www/npanel && php artisan schedule:run >> /dev/null 2>&1
```

### 14. Optimize Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 15. Create First User

```bash
php artisan tinker
```

```php
$user = new App\Models\User();
$user->name = 'Admin';
$user->email = 'admin@yourdomain.com';
$user->password = bcrypt('your_password');
$user->save();
exit
```

### 16. Setup SSL for Panel (Optional)

```bash
sudo certbot --nginx -d panel.yourdomain.com
```

Or use acme.sh:
```bash
~/.acme.sh/acme.sh --issue -d panel.yourdomain.com -w /var/www/npanel/public
~/.acme.sh/acme.sh --install-cert -d panel.yourdomain.com \
    --cert-file /etc/letsencrypt/live/panel.yourdomain.com/cert.pem \
    --key-file /etc/letsencrypt/live/panel.yourdomain.com/privkey.pem \
    --fullchain-file /etc/letsencrypt/live/panel.yourdomain.com/fullchain.pem \
    --reloadcmd "systemctl reload nginx"
```

## ✅ Verify Installation

1. Visit `https://panel.yourdomain.com`
2. Login with admin credentials
3. Create a test domain
4. Check queue worker: `sudo supervisorctl status`
5. Check logs: `tail -f /var/www/npanel/storage/logs/laravel.log`

## 🔧 Troubleshooting

### Queue not processing?
```bash
sudo supervisorctl restart npanel-worker:*
```

### Permission errors?
```bash
sudo chown -R www-data:www-data /var/www/npanel/storage
sudo chmod -R 775 /var/www/npanel/storage
```

### Nginx config test fails?
```bash
sudo nginx -t
# Fix any syntax errors shown
```

### PHP-FPM not starting?
```bash
sudo systemctl status php8.3-fpm
sudo journalctl -u php8.3-fpm
```

## 📚 Next Steps

- Read the [User Guide](USAGE.md)
- Configure domain DNS to point to your server
- Set up automatic backups
- Review security settings in `config/npanel.php`

## 🆘 Support

- GitHub Issues: https://github.com/marion909/npanel/issues
- Documentation: https://github.com/marion909/npanel/wiki
