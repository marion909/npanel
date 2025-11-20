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
    
    # Check if this is Ubuntu/Debian based system
    if [ -f /etc/debian_version ]; then
        # Try to add ondrej/php repository
        if command -v add-apt-repository &> /dev/null; then
            add-apt-repository ppa:ondrej/php -y 2>/dev/null || {
                print_warning "Could not add PPA, trying alternative method"
                # Alternative method for systems without add-apt-repository
                apt install -y lsb-release ca-certificates apt-transport-https
                wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg 2>/dev/null || true
                echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list
            }
        else
            print_warning "add-apt-repository not available, using direct repository add"
            apt install -y lsb-release ca-certificates apt-transport-https curl
            curl -sSL https://packages.sury.org/php/README.txt
            wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
            echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list
        fi
        apt update
    else
        print_error "This script only supports Debian/Ubuntu based systems"
        exit 1
    fi
    
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

setup_database() {
    print_status "Setting up database..."
    
    # Generate random password
    DB_PASSWORD=$(openssl rand -base64 32)
    
    mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
    mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
    
    print_success "Database created"
    print_warning "Database credentials:"
    echo "Database: ${DB_NAME}"
    echo "Username: ${DB_USER}"
    echo "Password: ${DB_PASSWORD}"
    echo ""
    echo "SAVE THESE CREDENTIALS! They will be written to .env file."
    
    # Store for later use
    export NPANEL_DB_PASSWORD="${DB_PASSWORD}"
}

install_npanel() {
    print_status "Installing nPanel..."
    
    # Clone repository
    if [ ! -d "${INSTALL_DIR}" ]; then
        git clone https://github.com/marion909/npanel.git "${INSTALL_DIR}"
    else
        print_warning "Directory ${INSTALL_DIR} already exists, skipping clone"
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
    
    # Optimize Laravel
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    print_success "nPanel installed"
}

configure_catchall() {
    print_status "Configuring catchall for unmapped domains..."
    
    # Create SSL directory and self-signed certificate
    mkdir -p /etc/nginx/ssl
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/nginx/ssl/default.key \
        -out /etc/nginx/ssl/default.crt \
        -subj '/C=AT/ST=Vienna/L=Vienna/O=nPanel/CN=default' \
        2>/dev/null
    
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
    
    # Create catchall Nginx config
    cat > "${NGINX_SITES_AVAILABLE}/000-default-catchall.conf" <<'EOFCATCHALL'
# Default catchall server for unmapped domains
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root /var/www/html/error;
    
    error_page 404 /404.html;
    location = /404.html { internal; }
    location / { return 404; }
}

server {
    listen 443 ssl default_server;
    listen [::]:443 ssl default_server;
    http2 on;
    server_name _;
    
    ssl_certificate /etc/nginx/ssl/default.crt;
    ssl_certificate_key /etc/nginx/ssl/default.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    root /var/www/html/error;
    error_page 404 /404.html;
    location = /404.html { internal; }
    location / { return 404; }
}
EOFCATCHALL
    
    # Enable catchall
    ln -sf "${NGINX_SITES_AVAILABLE}/000-default-catchall.conf" "${NGINX_SITES_ENABLED}/000-default-catchall.conf"
    
    print_success "Catchall configuration created"
}

configure_nginx() {
    print_status "Configuring Nginx for nPanel..."
    
    # Prompt for domain (or use specific domain)
    read -p "Enter domain for nPanel: " PANEL_DOMAIN
    if [ -z "$PANEL_DOMAIN" ]; then
        print_error "Domain is required"
        exit 1
    fi
    
    SERVER_NAME_LINE="server_name ${PANEL_DOMAIN} www.${PANEL_DOMAIN};"
    
    cat > "${NGINX_SITES_AVAILABLE}/npanel.conf" <<EOFNGINX
server {
    listen 80;
    listen [::]:80;
    ${SERVER_NAME_LINE}
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
    
    # Remove default site
    rm -f "${NGINX_SITES_ENABLED}/default"
    
    # Enable site
    ln -sf "${NGINX_SITES_AVAILABLE}/npanel.conf" "${NGINX_SITES_ENABLED}/npanel.conf"
    
    # Test and reload Nginx
    nginx -t
    systemctl reload nginx
    
    print_success "Nginx configured for ${PANEL_DOMAIN}"
    
    # Only offer SSL if a domain was provided
    if [ "${PANEL_DOMAIN}" != "_" ]; then
        read -p "Do you want to install SSL certificate now? (y/n): " INSTALL_SSL
        if [ "${INSTALL_SSL}" = "y" ] || [ "${INSTALL_SSL}" = "Y" ]; then
            install_ssl "${PANEL_DOMAIN}"
        fi
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
user=www-data
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
    setup_database
    install_npanel
    configure_sudo_permissions
    configure_catchall
    configure_nginx
    configure_supervisor
    configure_cron
    create_admin_user
    print_final_info
    
    print_success "Installation completed successfully!"
}

# Run main function
main
