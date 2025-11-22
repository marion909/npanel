#!/bin/bash

###############################################################################
# nPanel Installation Script
# Automated installation script for Ubuntu/Debian systems
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PHP_VERSIONS=("7.4" "8.0" "8.1" "8.2" "8.3")
DEFAULT_PHP_VERSION="8.3"
INSTALL_DIR="/var/www/npanel"
NGINX_SITES_AVAILABLE="/etc/nginx/sites-available"
NGINX_SITES_ENABLED="/etc/nginx/sites-enabled"
DB_NAME="npanel"
DB_USER="npanel_user"

# Functions
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_root() {
    if [ "$EUID" -ne 0 ]; then
        print_error "This script must be run as root"
        exit 1
    fi
}

install_system_dependencies() {
    print_status "Updating system packages..."
    apt update && apt upgrade -y
    
    print_status "Installing system dependencies..."
    # Install packages individually to handle missing ones gracefully
    apt install -y curl wget git unzip supervisor || true
    
    # software-properties-common might not exist on all systems
    if apt-cache show software-properties-common >/dev/null 2>&1; then
        apt install -y software-properties-common
    else
        print_warning "software-properties-common not available, skipping"
    fi
    
    # cron might be named differently or already installed
    if ! command -v crontab &> /dev/null; then
        apt install -y cron || apt install -y cronie || print_warning "Could not install cron"
    fi
    
    print_success "System dependencies installed"
}

install_nginx() {
    print_status "Installing Nginx..."
    apt install -y nginx
    systemctl enable nginx
    systemctl start nginx
    
    print_success "Nginx installed and started"
}

install_redis() {
    print_status "Installing Redis..."
    apt install -y redis-server
    systemctl enable redis-server
    systemctl start redis-server
    
    print_success "Redis installed and started"
}

install_mysql() {
    print_status "Installing MySQL/MariaDB..."
    
    # Try to install MySQL, if it fails use MariaDB
    if apt install -y mysql-server 2>/dev/null; then
        print_status "MySQL installed successfully"
        DB_SERVICE="mysql"
    else
        print_status "MySQL not available, installing MariaDB..."
        apt install -y mariadb-server
        DB_SERVICE="mariadb"
    fi
    
    systemctl enable $DB_SERVICE 2>/dev/null || systemctl enable mysql 2>/dev/null
    systemctl start $DB_SERVICE 2>/dev/null || systemctl start mysql 2>/dev/null
    
    print_success "Database server installed and started"
}

install_php_versions() {
    print_status "Adding PHP repository..."
    
    # Check if this is Ubuntu or Debian
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS_ID=$ID
    else
        print_error "Cannot determine operating system"
        exit 1
    fi
    
    # Install prerequisites
    apt install -y lsb-release ca-certificates apt-transport-https wget gnupg2
    
    if [ "$OS_ID" = "ubuntu" ]; then
        # Ubuntu: Use ondrej/php PPA
        print_status "Detected Ubuntu, adding ondrej/php PPA..."
        if command -v add-apt-repository &> /dev/null; then
            add-apt-repository ppa:ondrej/php -y
        else
            apt install -y software-properties-common
            add-apt-repository ppa:ondrej/php -y
        fi
    elif [ "$OS_ID" = "debian" ]; then
        # Debian: Use Sury PHP repository
        print_status "Detected Debian, adding Sury PHP repository..."
        wget -qO /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
        echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
    else
        print_error "Unsupported operating system: $OS_ID"
        exit 1
    fi
    
    apt update
    
    for version in "${PHP_VERSIONS[@]}"; do
        print_status "Installing PHP ${version}..."
        apt install -y \
            php${version}-cli \
            php${version}-fpm \
            php${version}-mysql \
            php${version}-sqlite3 \
            php${version}-mbstring \
            php${version}-xml \
            php${version}-curl \
            php${version}-gd \
            php${version}-zip \
            php${version}-redis \
            php${version}-bcmath \
            php${version}-intl
        
        systemctl enable php${version}-fpm
        systemctl start php${version}-fpm
        
        print_success "PHP ${version} installed and started"
    done
}

install_composer() {
    print_status "Installing Composer..."
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
    
    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        print_error "Composer installer corrupt"
        rm composer-setup.php
        exit 1
    fi
    
    php composer-setup.php --quiet
    rm composer-setup.php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    
    print_success "Composer installed"
}

install_nodejs() {
    print_status "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt install -y nodejs
    
    print_success "Node.js $(node -v) installed"
}

install_acme_sh() {
    print_status "Installing acme.sh..."
    
    # Prompt for email
    read -p "Enter email for SSL certificates: " ACME_EMAIL
    
    curl https://get.acme.sh | sh -s email="${ACME_EMAIL}"
    
    # Source acme.sh
    if [ -f ~/.acme.sh/acme.sh.env ]; then
        . ~/.acme.sh/acme.sh.env
    fi
    
    print_success "acme.sh installed"
}

install_wpcli() {
    print_status "Installing WP-CLI for WordPress management..."
    
    # Download WP-CLI
    curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    
    # Make it executable
    chmod +x wp-cli.phar
    
    # Move to system path
    mv wp-cli.phar /usr/local/bin/wp
    
    # Verify installation
    if wp --info &> /dev/null; then
        print_success "WP-CLI installed successfully"
    else
        print_warning "WP-CLI installation may have issues, but continuing..."
    fi
}

setup_database() {
    print_status "Setting up database..."
    
    # Generate random password for nPanel database
    DB_PASSWORD=$(openssl rand -base64 32)
    
    # Generate random password for MySQL admin user
    MYSQL_ADMIN_PASS=$(openssl rand -base64 32)
    
    # Check if we can access MySQL without password (unix socket)
    if mysql -e "SELECT 1;" 2>/dev/null; then
        MYSQL_CMD="mysql"
    else
        print_warning "Cannot access MySQL without password"
        read -sp "Enter MySQL root password (leave empty to skip): " MYSQL_ROOT_PASS
        echo ""
        if [ -n "$MYSQL_ROOT_PASS" ]; then
            MYSQL_CMD="mysql -u root -p${MYSQL_ROOT_PASS}"
        else
            print_error "Cannot proceed without MySQL access"
            exit 1
        fi
    fi
    
    # Create nPanel admin user for database management
    print_status "Creating MySQL admin user for nPanel..."
    $MYSQL_CMD -e "CREATE USER IF NOT EXISTS 'npanel_admin'@'localhost' IDENTIFIED BY '${MYSQL_ADMIN_PASS}';"
    $MYSQL_CMD -e "GRANT ALL PRIVILEGES ON *.* TO 'npanel_admin'@'localhost' WITH GRANT OPTION;"
    $MYSQL_CMD -e "FLUSH PRIVILEGES;"
    
    print_success "MySQL admin user created"
    print_warning "MySQL Admin Credentials:"
    echo "Username: npanel_admin"
    echo "Password: ${MYSQL_ADMIN_PASS}"
    echo ""
    
    # Store for later use
    export NPANEL_MYSQL_ROOT_PASSWORD="${MYSQL_ADMIN_PASS}"
    export NPANEL_MYSQL_ROOT_USERNAME="npanel_admin"
}

install_npanel() {
    print_status "Installing nPanel..."
    
    # Clone repository
    if [ ! -d "${INSTALL_DIR}" ]; then
        git clone https://github.com/marion909/npanel.git "${INSTALL_DIR}"
    else
        print_warning "Directory ${INSTALL_DIR} already exists, updating..."
        cd "${INSTALL_DIR}"
        # Reset any local changes and pull latest
        git reset --hard
        git pull
    fi
    
    cd "${INSTALL_DIR}"
    
    # Mark directory as safe for git
    git config --global --add safe.directory "${INSTALL_DIR}"
    
    # Install PHP dependencies
    print_status "Installing PHP dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    
    # Install Node dependencies
    print_status "Installing Node.js dependencies..."
    npm install
    npm run build
    
    # Create .env file
    if [ ! -f .env ]; then
        print_status "Creating .env file..."
        cp .env.example .env
        
        # Generate app key
        php artisan key:generate --force
        
        # Update database credentials for SQLite (default)
        sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=sqlite|" .env
        sed -i "s|DB_DATABASE=.*|DB_DATABASE=${INSTALL_DIR}/database/database.sqlite|" .env
        
        # Set queue connection to redis
        sed -i "s/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/" .env
        
        # Add MySQL root credentials for database management
        echo "" >> .env
        echo "# MySQL Root Connection for Database Management" >> .env
        echo "MYSQL_ROOT_HOST=127.0.0.1" >> .env
        echo "MYSQL_ROOT_PORT=3306" >> .env
        echo "MYSQL_ROOT_USERNAME=${NPANEL_MYSQL_ROOT_USERNAME}" >> .env
        echo "MYSQL_ROOT_PASSWORD=${NPANEL_MYSQL_ROOT_PASSWORD}" >> .env
    fi
    
    # Create SQLite database file
    print_status "Creating SQLite database..."
    touch "${INSTALL_DIR}/database/database.sqlite"
    chown www-data:www-data "${INSTALL_DIR}/database/database.sqlite"
    chmod 664 "${INSTALL_DIR}/database/database.sqlite"
    
    # Run migrations
    print_status "Running database migrations..."
    php artisan migrate --force
    
    # Set permissions
    print_status "Setting permissions..."
    chown -R www-data:www-data "${INSTALL_DIR}"
    chmod -R 755 "${INSTALL_DIR}"
    chmod -R 775 "${INSTALL_DIR}/storage"
    chmod -R 775 "${INSTALL_DIR}/bootstrap/cache"
    chmod -R 775 "${INSTALL_DIR}/database"
    
    # Clear all caches
    print_status "Clearing caches..."
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
    
    # Remove cache files manually to avoid conflicts
    rm -rf bootstrap/cache/*.php
    
    # Optimize Laravel
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    print_success "nPanel installed"
}

get_server_ip() {
    # Try to get public IPv4 address explicitly
    SERVER_IP=$(curl -4 -s ifconfig.me || curl -4 -s icanhazip.com || hostname -I | awk '{print $1}' | grep -oE '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+')
    
    if [ -z "$SERVER_IP" ]; then
        echo "127.0.0.1"
    else
        echo "${SERVER_IP}"
    fi
}

configure_catchall() {
    print_status "Configuring catchall for unmapped domains..."
    
    # Get server IP
    SERVER_IP=$(get_server_ip)
    print_status "Detected server IP: ${SERVER_IP}"
    
    # Create SSL directory and self-signed certificate
    mkdir -p /etc/nginx/ssl
    if [ ! -f /etc/nginx/ssl/default.crt ]; then
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout /etc/nginx/ssl/default.key \
            -out /etc/nginx/ssl/default.crt \
            -subj '/C=AT/ST=Vienna/L=Vienna/O=nPanel/CN=default' \
            2>/dev/null
    fi
    
    # Create error directory and 404 page
    mkdir -p /var/www/html/error
    cat > /var/www/html/error/404.html <<'EOF404'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain nicht konfiguriert</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; display: flex; align-items: center;
            justify-content: center; padding: 20px;
        }
        .container {
            background: white; border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 60px 40px; max-width: 600px; text-align: center;
        }
        .error-code { font-size: 120px; font-weight: 700; color: #667eea; }
        h1 { font-size: 32px; color: #2d3748; margin: 20px 0; }
        p { font-size: 18px; color: #718096; line-height: 1.6; margin-bottom: 15px; }
        .domain { font-weight: 600; color: #667eea; word-break: break-all; }
        .info-box {
            background: #f7fafc; border-left: 4px solid #667eea;
            padding: 20px; margin-top: 30px; text-align: left;
        }
        .info-box h2 { font-size: 18px; color: #2d3748; margin-bottom: 10px; }
        .info-box ul { list-style: none; padding-left: 0; }
        .info-box li { color: #4a5568; margin-bottom: 8px; padding-left: 25px; position: relative; }
        .info-box li:before { content: "✓"; position: absolute; left: 0; color: #667eea; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-code">404</div>
        <h1>Domain nicht konfiguriert</h1>
        <p>Die aufgerufene Domain <span class="domain" id="hostname"></span> ist auf diesem Server nicht eingerichtet.</p>
        <div class="info-box">
            <h2>Mögliche Gründe:</h2>
            <ul>
                <li>Die Domain wurde noch nicht im Hosting-Panel hinzugefügt</li>
                <li>Die DNS-Einstellungen sind noch nicht vollständig propagiert</li>
                <li>Die Domain-Konfiguration wurde gelöscht oder ist deaktiviert</li>
            </ul>
        </div>
        <p style="margin-top: 30px; font-size: 14px; color: #a0aec0;">
            Wenn Sie der Website-Betreiber sind, melden Sie sich im Hosting-Panel an und fügen Sie diese Domain hinzu.
        </p>
    </div>
    <script>document.getElementById('hostname').textContent = window.location.hostname;</script>
</body>
</html>
EOF404
    
    chown -R www-data:www-data /var/www/html/error
    
    # Create main npanel config with IP access, phpMyAdmin and catchall
    # Copy template and replace SERVER_IP placeholder
    cp "${INSTALL_DIR}/config/nginx/npanel.conf" "${NGINX_SITES_AVAILABLE}/npanel.conf"
    sed -i "s/SERVER_IP/${SERVER_IP}/g" "${NGINX_SITES_AVAILABLE}/npanel.conf"
    
    # Remove default site
    rm -f "${NGINX_SITES_ENABLED}/default"
    
    # Enable site
    ln -sf "${NGINX_SITES_AVAILABLE}/npanel.conf" "${NGINX_SITES_ENABLED}/npanel.conf"
    
    print_success "Catchall and IP access configured"
}

configure_nginx() {
    print_status "Configuring Nginx for nPanel domain..."
    
    # Prompt for domain (optional - if provided, creates separate vhost)
    read -p "Enter domain for nPanel (or press Enter to skip): " PANEL_DOMAIN
    
    if [ -n "$PANEL_DOMAIN" ]; then
        # Create separate vhost for panel domain
        cat > "${NGINX_SITES_AVAILABLE}/${PANEL_DOMAIN}.conf" <<EOFNGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${PANEL_DOMAIN} www.${PANEL_DOMAIN};
    root ${INSTALL_DIR}/public;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    index index.php index.html;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php${DEFAULT_PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOFNGINX
        
        # Enable panel domain site
        ln -sf "${NGINX_SITES_AVAILABLE}/${PANEL_DOMAIN}.conf" "${NGINX_SITES_ENABLED}/${PANEL_DOMAIN}.conf"
        
        # Test and reload Nginx
        nginx -t
        systemctl reload nginx
        
        print_success "Nginx configured for ${PANEL_DOMAIN}"
        
        # Offer SSL for panel domain
        read -p "Do you want to install SSL certificate for ${PANEL_DOMAIN} now? (y/n): " INSTALL_SSL
        if [ "${INSTALL_SSL}" = "y" ] || [ "${INSTALL_SSL}" = "Y" ]; then
            install_ssl "${PANEL_DOMAIN}"
        fi
    else
        print_success "Panel accessible via server IP only"
    fi
}

install_ssl() {
    local domain=$1
    print_status "Installing SSL certificate for ${domain}..."
    
    if [ -f ~/.acme.sh/acme.sh ]; then
        # Create letsencrypt directory
        mkdir -p /etc/letsencrypt/live/${domain}
        
        ~/.acme.sh/acme.sh --issue -d "${domain}" -w "${INSTALL_DIR}/public"
        ~/.acme.sh/acme.sh --install-cert -d "${domain}" \
            --cert-file /etc/letsencrypt/live/${domain}/cert.pem \
            --key-file /etc/letsencrypt/live/${domain}/privkey.pem \
            --fullchain-file /etc/letsencrypt/live/${domain}/fullchain.pem \
            --reloadcmd "systemctl reload nginx"
        
        # Update Nginx config with SSL
        cat > "${NGINX_SITES_AVAILABLE}/npanel.conf" <<EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${domain};
    return 301 https://\\\$server_name\\\$request_uri;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name ${domain};
    root ${INSTALL_DIR}/public;

    ssl_certificate /etc/letsencrypt/live/${domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${domain}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    index index.php index.html;

    charset utf-8;

    location / {
        try_files \\\$uri \\\$uri/ /index.php?\\\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\\\$ {
        fastcgi_pass unix:/var/run/php/php${DEFAULT_PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \\\$realpath_root\\\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
        
        nginx -t && systemctl reload nginx
        print_success "SSL certificate installed"
    else
        print_error "acme.sh not found"
    fi
}

configure_sudo_permissions() {
    print_status "Configuring sudo permissions for www-data..."
    
    cat > /etc/sudoers.d/npanel <<EOF
# Allow www-data to manage nginx and PHP-FPM services without password
www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx, /bin/systemctl reload nginx, /bin/systemctl reload php*-fpm, /usr/bin/systemctl reload nginx, /usr/bin/systemctl reload php*-fpm
EOF
    
    chmod 0440 /etc/sudoers.d/npanel
    
    # Verify sudoers file is valid
    if visudo -c; then
        print_success "Sudo permissions configured"
    else
        print_error "Sudoers file is invalid, removing..."
        rm -f /etc/sudoers.d/npanel
        exit 1
    fi
}

configure_supervisor() {
    print_status "Configuring Supervisor for queue workers..."
    
    cat > /etc/supervisor/conf.d/npanel-worker.conf <<EOF
[program:npanel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${INSTALL_DIR}/artisan queue:work --tries=3 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=root
numprocs=2
redirect_stderr=true
stdout_logfile=${INSTALL_DIR}/storage/logs/worker.log
stopwaitsecs=3600
EOF
    
    supervisorctl reread
    supervisorctl update
    supervisorctl start npanel-worker:*
    
    print_success "Supervisor configured"
}

configure_cron() {
    print_status "Configuring cron for scheduled tasks..."
    
    # Add Laravel scheduler
    (crontab -l 2>/dev/null; echo "* * * * * cd ${INSTALL_DIR} && php artisan schedule:run >> /dev/null 2>&1") | crontab -
    
    print_success "Cron configured"
}

create_admin_user() {
    print_status "Creating admin user..."
    
    read -p "Enter admin name: " ADMIN_NAME
    read -p "Enter admin email: " ADMIN_EMAIL
    read -sp "Enter admin password: " ADMIN_PASSWORD
    echo ""
    
    cd "${INSTALL_DIR}"
    php artisan tinker --execute="
        \$user = new App\Models\User();
        \$user->name = '${ADMIN_NAME}';
        \$user->email = '${ADMIN_EMAIL}';
        \$user->password = bcrypt('${ADMIN_PASSWORD}');
        \$user->save();
        echo 'Admin user created successfully';
    "
    
    print_success "Admin user created"
}

print_final_info() {
    echo ""
    echo "=========================================="
    print_success "nPanel Installation Complete!"
    echo "=========================================="
    echo ""
    echo "Panel URL: http://${PANEL_DOMAIN} (or https:// if SSL was installed)"
    echo ""
    echo "Admin Credentials:"
    echo "Email: ${ADMIN_EMAIL}"
    echo "Password: [the password you entered]"
    echo ""
    echo "Database Credentials:"
    echo "Database: ${DB_NAME}"
    echo "Username: ${DB_USER}"
    echo "Password: ${NPANEL_DB_PASSWORD}"
    echo ""
    echo "Important Files:"
    echo "- Panel directory: ${INSTALL_DIR}"
    echo "- Nginx config: ${NGINX_SITES_AVAILABLE}/npanel.conf"
    echo "- Environment file: ${INSTALL_DIR}/.env"
    echo ""
    echo "Useful Commands:"
    echo "- Check queue workers: sudo supervisorctl status"
    echo "- View logs: tail -f ${INSTALL_DIR}/storage/logs/laravel.log"
    echo "- Restart queue: sudo supervisorctl restart npanel-worker:*"
    echo ""
    print_warning "Please save the database password shown above!"
    echo ""
}

setup_mail_database() {
    print_status "Setting up mail server database..."
    
    # Generate random password for mail database
    MAIL_DB_PASSWORD=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-25)
    MAIL_DB_NAME="npanel_mail"
    MAIL_DB_USER="npanel_mail"
    
    # Create mail database and user
    print_status "Creating mail database ${MAIL_DB_NAME}..."
    
    if [ -n "${NPANEL_MYSQL_ROOT_PASSWORD}" ]; then
        MYSQL_CMD="mysql -u ${NPANEL_MYSQL_ROOT_USERNAME} -p${NPANEL_MYSQL_ROOT_PASSWORD}"
    else
        MYSQL_CMD="mysql"
    fi
    
    $MYSQL_CMD <<EOF
CREATE DATABASE IF NOT EXISTS ${MAIL_DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${MAIL_DB_USER}'@'localhost' IDENTIFIED BY '${MAIL_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${MAIL_DB_NAME}.* TO '${MAIL_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
    
    print_success "Mail database created"
    
    # Create mail database tables
    print_status "Creating mail server tables..."
    
    $MYSQL_CMD ${MAIL_DB_NAME} <<'EOF'
-- Domains table (simplified for Postfix/Dovecot)
CREATE TABLE IF NOT EXISTS domains (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    domain_name VARCHAR(255) NOT NULL UNIQUE,
    status ENUM('pending', 'active', 'suspended', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_domain_name (domain_name),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mailboxes table
CREATE TABLE IF NOT EXISTS mailboxes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_encrypted VARCHAR(255) NOT NULL,
    quota_mb INT NOT NULL DEFAULT 1000,
    used_mb INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mail aliases table (includes catch-all)
CREATE TABLE IF NOT EXISTS mail_aliases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(255) NOT NULL,
    destination TEXT NOT NULL,
    type ENUM('alias', 'catchall') DEFAULT 'alias',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    INDEX idx_source (source),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF
    
    print_success "Mail server tables created"
    
    # Add mail database credentials to .env
    print_status "Adding mail database credentials to .env..."
    
    if ! grep -q "MAIL_DB_HOST" "${INSTALL_DIR}/.env"; then
        cat >> "${INSTALL_DIR}/.env" <<EOF

# Mail Server Database Configuration (for Postfix/Dovecot)
MAIL_DB_HOST=127.0.0.1
MAIL_DB_PORT=3306
MAIL_DB_DATABASE=${MAIL_DB_NAME}
MAIL_DB_USERNAME=${MAIL_DB_USER}
MAIL_DB_PASSWORD=${MAIL_DB_PASSWORD}
EOF
        print_success "Mail database credentials added to .env"
    else
        print_warning "Mail database credentials already exist in .env"
    fi
    
    # Store credentials for later use
    export MAIL_DB_NAME
    export MAIL_DB_USER
    export MAIL_DB_PASSWORD
    
    print_success "Mail database setup completed"
}

install_mail_server() {
    print_status "Installing mail server components (Postfix, Dovecot, OpenDKIM, Roundcube)..."
    
    # Check if user wants to install mail server
    read -p "Do you want to install the mail server? (y/n): " -n 1 -r
    echo ""
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_warning "Skipping mail server installation"
        return
    fi
    
    # Setup mail database first
    setup_mail_database
    
    print_status "Installing mail server packages..."
    
    # Install mail server packages
    DEBIAN_FRONTEND=noninteractive apt-get install -y \
        postfix \
        postfix-mysql \
        dovecot-core \
        dovecot-imapd \
        dovecot-lmtpd \
        dovecot-mysql \
        opendkim \
        opendkim-tools
    
    print_success "Mail server packages installed"
    
    # Create vmail user
    print_status "Creating vmail user..."
    if ! id -u vmail >/dev/null 2>&1; then
        groupadd -g 5000 vmail
        useradd -u 5000 -g vmail -s /usr/sbin/nologin -d /var/vmail -m vmail
        mkdir -p /var/vmail
        chown -R vmail:vmail /var/vmail
        chmod 750 /var/vmail
        print_success "vmail user created with UID 5000"
    else
        print_warning "vmail user already exists"
    fi
    
    # Configure Postfix
    print_status "Configuring Postfix..."
    postconf -e "myhostname=$(hostname -f)"
    postconf -e "mydestination=localhost"
    postconf -e "smtp_address_preference=ipv4"
    
    # Enable SASL authentication via Dovecot
    postconf -e "smtpd_sasl_type=dovecot"
    postconf -e "smtpd_sasl_path=private/auth"
    postconf -e "smtpd_sasl_auth_enable=yes"
    
    # Enable submission port (587) for authenticated SMTP
    print_status "Enabling submission port (587) for SMTP authentication..."
    if ! grep -q "^submission inet" /etc/postfix/master.cf; then
        cat >> /etc/postfix/master.cf << 'SUBMISSION_EOF'

# Submission port for authenticated SMTP (port 587)
submission inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=may
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_tls_auth_only=no
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
  -o smtpd_sender_login_maps=mysql:/etc/postfix/mysql/virtual-sender-login-maps.cf
  -o smtpd_sender_restrictions=reject_sender_login_mismatch
  -o smtpd_recipient_restrictions=reject_non_fqdn_recipient,reject_unknown_recipient_domain,permit_sasl_authenticated,reject
SUBMISSION_EOF
    fi
    
    # Create sender login maps
    mkdir -p /etc/postfix/mysql
    cat > /etc/postfix/mysql/virtual-sender-login-maps.cf << EOF
user = ${MAIL_DB_USERNAME}
password = ${MAIL_DB_PASSWORD}
hosts = 127.0.0.1
dbname = ${MAIL_DB_DATABASE}
query = SELECT email FROM mailboxes WHERE email='%s' AND status='active'
EOF
    chmod 640 /etc/postfix/mysql/virtual-sender-login-maps.cf
    chown root:postfix /etc/postfix/mysql/virtual-sender-login-maps.cf
    
    # Configure OpenDKIM
    print_status "Configuring OpenDKIM..."
    mkdir -p /etc/opendkim/keys
    chmod 750 /etc/opendkim/keys
    chown -R opendkim:opendkim /etc/opendkim
    
    # Enable and start services
    print_status "Starting mail services..."
    systemctl enable postfix dovecot opendkim
    systemctl start postfix dovecot opendkim
    
    # Run database migrations for mail tables
    print_status "Running mail database migrations..."
    cd "$INSTALL_DIR"
    sudo -u www-data php artisan migrate --force
    
    # Sync data to mail database
    print_status "Syncing data to mail database..."
    php artisan config:clear
    php artisan mail:sync-database || print_warning "Could not sync mail database (might be empty)"
    
    # Generate mail service configurations
    print_status "Generating mail service configurations..."
    php artisan mail:regenerate-configs || print_warning "Could not regenerate mail configs"
    
    # Reload services with new configs
    systemctl reload postfix dovecot opendkim
    
    # Install Roundcube webmail
    print_status "Installing Roundcube webmail..."
    install_roundcube
    
    print_success "Mail server installation completed!"
    print_status "You can now create mailboxes via the web interface"
    print_status "Webmail will be available at: https://webmail.$(hostname -f)"
}

install_roundcube() {
    local ROUNDCUBE_VERSION="1.6.5"
    local ROUNDCUBE_PATH="/var/www/roundcube"
    local WEBMAIL_DOMAIN="webmail.$(hostname -f)"
    
    # Check if already installed
    if [ -d "$ROUNDCUBE_PATH" ]; then
        print_warning "Roundcube already installed at $ROUNDCUBE_PATH"
        return
    fi
    
    # Download Roundcube
    print_status "Downloading Roundcube ${ROUNDCUBE_VERSION}..."
    cd /tmp
    wget -q "https://github.com/roundcube/roundcubemail/releases/download/${ROUNDCUBE_VERSION}/roundcubemail-${ROUNDCUBE_VERSION}-complete.tar.gz"
    
    # Extract
    print_status "Extracting Roundcube..."
    tar -xzf "roundcubemail-${ROUNDCUBE_VERSION}-complete.tar.gz" -C /var/www/
    mv "/var/www/roundcubemail-${ROUNDCUBE_VERSION}" "$ROUNDCUBE_PATH"
    rm "roundcubemail-${ROUNDCUBE_VERSION}-complete.tar.gz"
    
    # Set permissions
    chown -R www-data:www-data "$ROUNDCUBE_PATH"
    chmod 755 "$ROUNDCUBE_PATH"
    
    # Create ACME challenge directory with proper permissions
    mkdir -p "$ROUNDCUBE_PATH/.well-known/acme-challenge"
    chown -R www-data:www-data "$ROUNDCUBE_PATH/.well-known"
    chmod -R 755 "$ROUNDCUBE_PATH/.well-known"
    
    # Create Nginx vhost
    print_status "Creating Nginx vhost for $WEBMAIL_DOMAIN..."
    cat > /etc/nginx/sites-available/roundcube.conf <<'NGINX_EOF'
server {
    listen 80;
    server_name WEBMAIL_DOMAIN_PLACEHOLDER;

    # ACME challenge directory
    location /.well-known/acme-challenge/ {
        root /var/www/roundcube;
    }

    # Redirect HTTP to HTTPS
    location / {
        return 301 https://$server_name$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name WEBMAIL_DOMAIN_PLACEHOLDER;

    root /var/www/roundcube;
    index index.php index.html;

    # SSL Configuration (self-signed initially)
    ssl_certificate /etc/ssl/certs/ssl-cert-snakeoil.pem;
    ssl_certificate_key /etc/ssl/private/ssl-cert-snakeoil.key;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|svn|git) {
        deny all;
    }
}
NGINX_EOF
    
    # Replace placeholder
    sed -i "s/WEBMAIL_DOMAIN_PLACEHOLDER/$WEBMAIL_DOMAIN/g" /etc/nginx/sites-available/roundcube.conf
    
    # Enable site
    ln -sf /etc/nginx/sites-available/roundcube.conf /etc/nginx/sites-enabled/00-roundcube.conf
    
    # Test and reload Nginx
    nginx -t && systemctl reload nginx
    
    # Configure Roundcube
    print_status "Configuring Roundcube..."
    cat > "$ROUNDCUBE_PATH/config/config.inc.php" <<'PHP_EOF'
<?php
$config = [];

// Database connection
$config['db_dsnw'] = 'mysql://DB_USER_PLACEHOLDER:DB_PASS_PLACEHOLDER@localhost/DB_NAME_PLACEHOLDER';

// IMAP settings - connect to local Dovecot
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

// SMTP settings - connect to local Postfix
$config['smtp_host'] = 'localhost:587';
$config['smtp_user'] = '%u';
$config['smtp_pass'] = '%p';
$config['smtp_conn_options'] = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
];

// Security
$config['des_key'] = 'RANDOM_KEY_PLACEHOLDER';
$config['cipher_method'] = 'AES-256-CBC';
$config['username_domain'] = '';

// UI
$config['product_name'] = 'nPanel Webmail';
$config['support_url'] = '';
$config['skin'] = 'elastic';
$config['language'] = 'en_US';

// Identities
$config['identities_level'] = 0;

// Misc
$config['enable_installer'] = false;
$config['log_driver'] = 'syslog';
$config['syslog_facility'] = LOG_MAIL;
$config['session_lifetime'] = 30;

// Plugins
$config['plugins'] = [];
PHP_EOF
    
    # Generate random DES key
    local RANDOM_KEY=$(openssl rand -base64 24)
    
    # Replace placeholders
    sed -i "s/DB_USER_PLACEHOLDER/$DB_USER/g" "$ROUNDCUBE_PATH/config/config.inc.php"
    sed -i "s/DB_PASS_PLACEHOLDER/$DB_PASSWORD/g" "$ROUNDCUBE_PATH/config/config.inc.php"
    sed -i "s/DB_NAME_PLACEHOLDER/$DB_NAME/g" "$ROUNDCUBE_PATH/config/config.inc.php"
    sed -i "s/RANDOM_KEY_PLACEHOLDER/$RANDOM_KEY/g" "$ROUNDCUBE_PATH/config/config.inc.php"
    
    chown www-data:www-data "$ROUNDCUBE_PATH/config/config.inc.php"
    chmod 640 "$ROUNDCUBE_PATH/config/config.inc.php"
    
    print_success "Roundcube installed at $ROUNDCUBE_PATH"
    
    # Issue SSL certificate
    print_status "Issuing SSL certificate for $WEBMAIL_DOMAIN..."
    if [ -f "/root/.acme.sh/acme.sh" ]; then
        cd "$INSTALL_DIR"
        # Run as root, not www-data, because acme.sh is in /root
        php artisan npanel:roundcube-ssl --domain="$WEBMAIL_DOMAIN" || \
            print_warning "Failed to issue SSL certificate. You can try manually later with: php artisan npanel:roundcube-ssl"
    else
        print_warning "acme.sh not found. Roundcube will use self-signed certificate."
        print_status "You can issue a certificate later with: php artisan npanel:roundcube-ssl"
    fi
}

# Main installation flow
main() {
    echo ""
    echo "=========================================="
    echo "  nPanel Installation Script"
    echo "=========================================="
    echo ""
    
    check_root
    
    print_status "Starting installation..."
    echo ""
    
    install_system_dependencies
    install_nginx
    install_redis
    install_mysql
    install_php_versions
    install_composer
    install_nodejs
    install_acme_sh
    install_wpcli
    setup_database
    install_npanel
    configure_sudo_permissions
    configure_catchall
    configure_nginx
    configure_supervisor
    configure_cron
    create_admin_user
    install_mail_server
    print_final_info
    
    print_success "Installation completed successfully!"
}

# Run main function
main
