#!/bin/bash

###############################################################################
# nPanel Update Script
# Automated update script for existing nPanel installations
###############################################################################

set -e  # Exit on error

# Parse command line arguments
AUTO_YES=false
for arg in "$@"; do
    case $arg in
        -y|--auto-yes|--yes)
            AUTO_YES=true
            shift
            ;;
    esac
done

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
INSTALL_DIR="/var/www/npanel"
BACKUP_DIR="/var/backups/npanel"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

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

check_installation() {
    if [ ! -d "${INSTALL_DIR}" ]; then
        print_error "nPanel installation not found at ${INSTALL_DIR}"
        exit 1
    fi
    
    if [ ! -f "${INSTALL_DIR}/.env" ]; then
        print_error ".env file not found. Please check your installation."
        exit 1
    fi
}

create_backup() {
    print_status "Creating backup..."
    
    # Create backup directory
    mkdir -p "${BACKUP_DIR}"
    
    # Backup database
    print_status "Backing up SQLite database..."
    if [ -f "${INSTALL_DIR}/database/database.sqlite" ]; then
        cp "${INSTALL_DIR}/database/database.sqlite" "${BACKUP_DIR}/database_${TIMESTAMP}.sqlite"
        print_success "Database backed up to ${BACKUP_DIR}/database_${TIMESTAMP}.sqlite"
    fi
    
    # Backup .env file
    print_status "Backing up .env file..."
    cp "${INSTALL_DIR}/.env" "${BACKUP_DIR}/.env_${TIMESTAMP}"
    print_success ".env backed up to ${BACKUP_DIR}/.env_${TIMESTAMP}"
    
    # Backup storage directory (uploaded files, logs)
    print_status "Backing up storage directory..."
    tar -czf "${BACKUP_DIR}/storage_${TIMESTAMP}.tar.gz" -C "${INSTALL_DIR}" storage 2>/dev/null || true
    print_success "Storage backed up to ${BACKUP_DIR}/storage_${TIMESTAMP}.tar.gz"
    
    print_success "Backup completed"
}

enable_maintenance_mode() {
    print_status "Enabling maintenance mode..."
    cd "${INSTALL_DIR}"
    php artisan down || print_warning "Could not enable maintenance mode"
}

disable_maintenance_mode() {
    print_status "Disabling maintenance mode..."
    cd "${INSTALL_DIR}"
    php artisan up || print_warning "Could not disable maintenance mode"
}

stop_queue_workers() {
    print_status "Stopping queue workers..."
    supervisorctl stop npanel-worker:* 2>/dev/null || print_warning "Queue workers not found"
}

start_queue_workers() {
    print_status "Starting queue workers..."
    
    # Create supervisor config if it doesn't exist
    if [ ! -f /etc/supervisor/conf.d/npanel-worker.conf ]; then
        print_status "Creating supervisor configuration..."
        cat > /etc/supervisor/conf.d/npanel-worker.conf <<EOF
[program:npanel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${INSTALL_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
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
        print_success "Supervisor configuration created"
    fi
    
    supervisorctl reread 2>/dev/null || true
    supervisorctl update 2>/dev/null || true
    supervisorctl start npanel-worker:* 2>/dev/null || print_warning "Could not start queue workers"
}

pull_latest_code() {
    print_status "Pulling latest code from repository..."
    cd "${INSTALL_DIR}"
    
    # Remove update.sh if it exists (will be recreated from repo)
    rm -f update.sh
    
    # Stash any local changes
    git stash 2>/dev/null || true
    
    # Pull latest changes
    git pull origin main || {
        print_error "Failed to pull latest code"
        exit 1
    }
    
    print_success "Code updated"
}

update_dependencies() {
    print_status "Updating PHP dependencies..."
    cd "${INSTALL_DIR}"
    composer install --no-dev --optimize-autoloader --no-interaction
    
    print_status "Updating Node.js dependencies..."
    npm install
    
    print_success "Dependencies updated"
}

build_assets() {
    print_status "Building frontend assets..."
    cd "${INSTALL_DIR}"
    npm run build
    print_success "Assets built"
}

run_migrations() {
    print_status "Running database migrations..."
    cd "${INSTALL_DIR}"
    php artisan migrate --force
    print_success "Migrations completed"
}

clear_caches() {
    print_status "Clearing caches..."
    cd "${INSTALL_DIR}"
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
    print_success "Caches cleared"
}

optimize_laravel() {
    print_status "Optimizing Laravel..."
    cd "${INSTALL_DIR}"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan optimize
    print_success "Laravel optimized"
}

update_permissions() {
    print_status "Updating permissions..."
    cd "${INSTALL_DIR}"
    chown -R www-data:www-data "${INSTALL_DIR}"
    chmod -R 755 "${INSTALL_DIR}"
    chmod -R 775 "${INSTALL_DIR}/storage"
    chmod -R 775 "${INSTALL_DIR}/bootstrap/cache"
    chmod -R 775 "${INSTALL_DIR}/database"
    
    if [ -f "${INSTALL_DIR}/database/database.sqlite" ]; then
        chmod 664 "${INSTALL_DIR}/database/database.sqlite"
    fi
    
    print_success "Permissions updated"
}

restart_services() {
    print_status "Restarting services..."
    
    # Update Nginx configuration if template exists
    if [ -f "${INSTALL_DIR}/config/nginx/npanel.conf" ]; then
        print_status "Updating Nginx configuration..."
        # Get IPv4 address explicitly
        SERVER_IP=$(curl -4 -s ifconfig.me || curl -4 -s icanhazip.com || hostname -I | awk '{print $1}' | grep -oE '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+')
        
        if [ -z "$SERVER_IP" ]; then
            print_error "Could not determine server IPv4 address"
            SERVER_IP="127.0.0.1"
        fi
        
        print_status "Using server IP: ${SERVER_IP}"
        cp "${INSTALL_DIR}/config/nginx/npanel.conf" "/etc/nginx/sites-available/npanel.conf"
        sed -i "s/SERVER_IP/${SERVER_IP}/g" "/etc/nginx/sites-available/npanel.conf"
        
        # Test Nginx config
        if nginx -t 2>/dev/null; then
            print_success "Nginx configuration updated"
        else
            print_warning "Nginx configuration test failed, keeping old config"
        fi
    fi
    
    # Restart PHP-FPM
    for version in 7.4 8.0 8.1 8.2 8.3; do
        if systemctl is-active --quiet php${version}-fpm; then
            systemctl restart php${version}-fpm
            print_success "PHP ${version}-FPM restarted"
        fi
    done
    
    # Reload Nginx
    systemctl reload nginx
    print_success "Nginx reloaded"
}

check_env_variables() {
    print_status "Checking for new environment variables..."
    cd "${INSTALL_DIR}"
    
    # Check if MYSQL_ROOT credentials exist
    if ! grep -q "MYSQL_ROOT_PASSWORD" .env; then
        print_warning "MySQL root credentials not found in .env"
        print_status "Setting up MySQL admin user for database management..."
        
        # Generate secure password
        MYSQL_ADMIN_PASS=$(openssl rand -base64 24)
        
        # Create MySQL admin user
        mysql -e "CREATE USER IF NOT EXISTS 'npanel_admin'@'localhost' IDENTIFIED BY '${MYSQL_ADMIN_PASS}';" 2>/dev/null || \
        mysql -u root -e "CREATE USER IF NOT EXISTS 'npanel_admin'@'localhost' IDENTIFIED BY '${MYSQL_ADMIN_PASS}';" 2>/dev/null || {
            print_error "Failed to create MySQL admin user. Please run manually:"
            echo "sudo mysql -e \"CREATE USER 'npanel_admin'@'localhost' IDENTIFIED BY 'your_password';\""
            echo "sudo mysql -e \"GRANT ALL PRIVILEGES ON *.* TO 'npanel_admin'@'localhost' WITH GRANT OPTION;\""
            return 1
        }
        
        # Grant privileges
        mysql -e "GRANT ALL PRIVILEGES ON *.* TO 'npanel_admin'@'localhost' WITH GRANT OPTION;" 2>/dev/null || \
        mysql -u root -e "GRANT ALL PRIVILEGES ON *.* TO 'npanel_admin'@'localhost' WITH GRANT OPTION;" 2>/dev/null || {
            print_error "Failed to grant privileges to MySQL admin user"
            return 1
        }
        
        mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || mysql -u root -e "FLUSH PRIVILEGES;" 2>/dev/null
        
        # Add to .env
        echo "" >> .env
        echo "# MySQL Root Connection for Database Management" >> .env
        echo "MYSQL_ROOT_HOST=127.0.0.1" >> .env
        echo "MYSQL_ROOT_PORT=3306" >> .env
        echo "MYSQL_ROOT_USERNAME=npanel_admin" >> .env
        echo "MYSQL_ROOT_PASSWORD=${MYSQL_ADMIN_PASS}" >> .env
        
        print_success "MySQL admin user 'npanel_admin' created"
        print_success "Credentials added to .env"
        
        echo ""
        echo "=========================================="
        print_warning "MySQL Admin Credentials (SAVE THESE!):"
        echo "=========================================="
        echo "Username: npanel_admin"
        echo "Password: ${MYSQL_ADMIN_PASS}"
        echo "Host: 127.0.0.1"
        echo "Port: 3306"
        echo ""
        echo "These credentials are stored in: ${INSTALL_DIR}/.env"
        echo "=========================================="
        echo ""
        
        # Wait for user to see credentials (unless auto-yes is enabled)
        if [ "$AUTO_YES" = false ]; then
            read -p "Press Enter to continue after saving these credentials..."
        else
            print_status "Auto-yes mode: Continuing automatically..."
            sleep 2
        fi
    else
        print_success "MySQL root credentials already configured"
    fi
}

print_update_info() {
    echo ""
    echo "=========================================="
    print_success "nPanel Update Complete!"
    echo "=========================================="
    echo ""
    echo "Backup Location: ${BACKUP_DIR}"
    echo "Latest Backup: ${TIMESTAMP}"
    echo ""
    echo "Backup Files:"
    echo "- Database: database_${TIMESTAMP}.sqlite"
    echo "- Environment: .env_${TIMESTAMP}"
    echo "- Storage: storage_${TIMESTAMP}.tar.gz"
    echo ""
    echo "Useful Commands:"
    echo "- View logs: tail -f ${INSTALL_DIR}/storage/logs/laravel.log"
    echo "- Check queue workers: sudo supervisorctl status"
    echo "- Restart queue: sudo supervisorctl restart npanel-worker:*"
    echo ""
}

check_mail_server_update() {
    print_status "Checking mail server status..."
    
    # Check if mail server packages are installed
    if dpkg -l | grep -q "dovecot-core" && dpkg -l | grep -q "postfix"; then
        print_status "Mail server detected. Regenerating configurations..."
        
        cd "${INSTALL_DIR}"
        
        # Regenerate mail service configurations
        sudo -u www-data php artisan tinker --execute="
            try {
                \$postfixService = app(\App\Services\PostfixService::class);
                \$dovecotService = app(\App\Services\DovecotService::class);
                \$postfixService->generateConfigs();
                \$dovecotService->generateSqlConfig();
                echo 'Mail configurations updated\n';
            } catch (\Exception \$e) {
                echo 'Warning: Could not update mail configs: ' . \$e->getMessage() . '\n';
            }
        "
        
        # Reload mail services
        if systemctl is-active --quiet postfix; then
            systemctl reload postfix || print_warning "Could not reload Postfix"
        fi
        
        if systemctl is-active --quiet dovecot; then
            systemctl reload dovecot || print_warning "Could not reload Dovecot"
        fi
        
        # Check Roundcube installation
        check_roundcube_update
        
        print_success "Mail server configurations updated"
    else
        print_status "Mail server not installed."
        
        # Offer to install mail server
        if [ "$AUTO_YES" = false ]; then
            echo ""
            read -p "Would you like to install the mail server now? (y/n): " -n 1 -r
            echo ""
            if [[ $REPLY =~ ^[Yy]$ ]]; then
                install_mail_server
            else
                print_status "Skipping mail server installation."
            fi
        else
            print_status "Use --install-mail flag to install mail server during update."
        fi
    fi
}

install_mail_server() {
    print_status "Installing mail server components (Postfix, Dovecot, OpenDKIM, Roundcube)..."
    
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
    cd "${INSTALL_DIR}"
    sudo -u www-data php artisan migrate --force
    
    # Generate mail service configurations via Artisan
    print_status "Generating mail service configurations..."
    sudo -u www-data php artisan tinker --execute="
        \$postfixService = app(\App\Services\PostfixService::class);
        \$dovecotService = app(\App\Services\DovecotService::class);
        \$postfixService->generateConfigs();
        \$postfixService->updateMainConfig();
        \$dovecotService->generateAllConfigs();
        echo 'Mail configurations generated\n';
    "
    
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
    
    # Get database credentials from .env
    cd "${INSTALL_DIR}"
    DB_USER=$(grep DB_USERNAME .env | cut -d '=' -f2)
    DB_PASSWORD=$(grep DB_PASSWORD .env | cut -d '=' -f2)
    DB_NAME=$(grep DB_DATABASE .env | cut -d '=' -f2)
    
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
    cat > /etc/nginx/sites-available/roundcube.conf <<NGINX_EOF
server {
    listen 80;
    server_name ${WEBMAIL_DOMAIN};

    # ACME challenge directory
    location /.well-known/acme-challenge/ {
        root /var/www/roundcube;
    }

    # Redirect HTTP to HTTPS
    location / {
        return 301 https://\$server_name\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name ${WEBMAIL_DOMAIN};

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
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|svn|git) {
        deny all;
    }
}
NGINX_EOF
    
    # Enable site
    ln -sf /etc/nginx/sites-available/roundcube.conf /etc/nginx/sites-enabled/
    
    # Test and reload Nginx
    nginx -t && systemctl reload nginx
    
    # Configure Roundcube
    print_status "Configuring Roundcube..."
    
    # Generate random DES key
    local RANDOM_KEY=$(openssl rand -base64 24)
    
    cat > "$ROUNDCUBE_PATH/config/config.inc.php" <<PHP_EOF
<?php
\$config = [];

// Database connection
\$config['db_dsnw'] = 'mysql://${DB_USER}:${DB_PASSWORD}@localhost/${DB_NAME}';

// IMAP/SMTP settings
\$config['default_host'] = 'ssl://localhost';
\$config['default_port'] = 993;
\$config['smtp_host'] = 'tls://localhost';
\$config['smtp_port'] = 587;
\$config['smtp_user'] = '%u';
\$config['smtp_pass'] = '%p';

// Security
\$config['des_key'] = '${RANDOM_KEY}';
\$config['cipher_method'] = 'AES-256-CBC';

// UI
\$config['product_name'] = 'nPanel Webmail';
\$config['support_url'] = '';
\$config['skin'] = 'elastic';

// Misc
\$config['enable_installer'] = false;
\$config['log_driver'] = 'syslog';
\$config['syslog_facility'] = LOG_MAIL;
PHP_EOF
    
    chown www-data:www-data "$ROUNDCUBE_PATH/config/config.inc.php"
    chmod 640 "$ROUNDCUBE_PATH/config/config.inc.php"
    
    print_success "Roundcube installed at $ROUNDCUBE_PATH"
    
    # Install acme.sh if not present
    if [ ! -f "/root/.acme.sh/acme.sh" ]; then
        print_status "Installing acme.sh for SSL certificate management..."
        curl -s https://get.acme.sh | sh -s email=admin@$(hostname -f)
        
        # Source acme.sh environment
        if [ -f /root/.acme.sh/acme.sh.env ]; then
            . /root/.acme.sh/acme.sh.env
        fi
        
        print_success "acme.sh installed"
    fi
    
    # Issue SSL certificate
    print_status "Issuing SSL certificate for $WEBMAIL_DOMAIN..."
    if [ -f "/root/.acme.sh/acme.sh" ]; then
        cd "${INSTALL_DIR}"
        # Run as root, not www-data, because acme.sh is in /root
        php artisan npanel:roundcube-ssl --domain="$WEBMAIL_DOMAIN" || \
            print_warning "Failed to issue SSL certificate. You can try manually later with: php artisan npanel:roundcube-ssl"
    else
        print_warning "acme.sh installation failed. Roundcube will use self-signed certificate."
        print_status "You can install acme.sh manually: curl https://get.acme.sh | sh"
    fi
}

check_roundcube_update() {
    local ROUNDCUBE_PATH="/var/www/roundcube"
    local WEBMAIL_DOMAIN="webmail.$(hostname -f)"
    
    # Check if Roundcube is installed
    if [ ! -d "$ROUNDCUBE_PATH" ]; then
        print_status "Roundcube not installed. Skipping."
        return
    fi
    
    print_status "Checking Roundcube SSL certificate..."
    
    # Check if SSL certificate exists
    local CERT_DIR="/etc/letsencrypt/live/$WEBMAIL_DOMAIN"
    
    if [ -f "$CERT_DIR/fullchain.pem" ]; then
        # Certificate exists, check expiry
        local EXPIRY_DATE=$(openssl x509 -enddate -noout -in "$CERT_DIR/fullchain.pem" | cut -d= -f2)
        local EXPIRY_EPOCH=$(date -d "$EXPIRY_DATE" +%s)
        local NOW_EPOCH=$(date +%s)
        local DAYS_LEFT=$(( ($EXPIRY_EPOCH - $NOW_EPOCH) / 86400 ))
        
        if [ $DAYS_LEFT -lt 30 ]; then
            print_warning "SSL certificate expires in $DAYS_LEFT days. Consider renewing."
        else
            print_status "SSL certificate valid for $DAYS_LEFT days"
        fi
    else
        # No Let's Encrypt certificate, check if using self-signed
        local NGINX_CONF="/etc/nginx/sites-available/roundcube.conf"
        if [ -f "$NGINX_CONF" ] && grep -q "ssl-cert-snakeoil" "$NGINX_CONF"; then
            print_warning "Roundcube is using self-signed certificate"
            print_status "Issue SSL certificate with: php artisan npanel:roundcube-ssl"
            
            # Offer to issue certificate now
            if [ "$AUTO_YES" = false ]; then
                read -p "Would you like to issue SSL certificate now? (y/n): " -n 1 -r
                echo ""
                if [[ $REPLY =~ ^[Yy]$ ]]; then
                    cd "${INSTALL_DIR}"
                    sudo -u www-data php artisan npanel:roundcube-ssl --domain="$WEBMAIL_DOMAIN" || \
                        print_warning "Failed to issue SSL certificate"
                fi
            fi
        fi
    fi
    
    # Check Roundcube version
    if [ -f "$ROUNDCUBE_PATH/index.php" ]; then
        local CURRENT_VERSION=$(grep -oP "define\('RCMAIL_VERSION', '\K[^']+(?=')" "$ROUNDCUBE_PATH/program/include/iniset.php" 2>/dev/null || echo "unknown")
        print_status "Roundcube version: $CURRENT_VERSION"
    fi
}

rollback_instructions() {
    echo ""
    echo "=========================================="
    print_error "Update Failed!"
    echo "=========================================="
    echo ""
    echo "To rollback to previous version:"
    echo "1. Restore database: cp ${BACKUP_DIR}/database_${TIMESTAMP}.sqlite ${INSTALL_DIR}/database/database.sqlite"
    echo "2. Restore .env: cp ${BACKUP_DIR}/.env_${TIMESTAMP} ${INSTALL_DIR}/.env"
    echo "3. Restore storage: tar -xzf ${BACKUP_DIR}/storage_${TIMESTAMP}.tar.gz -C ${INSTALL_DIR}"
    echo "4. Run: cd ${INSTALL_DIR} && git reset --hard HEAD~1"
    echo "5. Restart services: systemctl reload nginx && supervisorctl restart npanel-worker:*"
    echo ""
}

# Main update flow
main() {
    echo ""
    echo "=========================================="
    echo "  nPanel Update Script"
    echo "=========================================="
    echo ""
    
    check_root
    check_installation
    
    print_status "Starting update process..."
    echo ""
    
    # Confirm update (unless auto-yes is enabled)
    if [ "$AUTO_YES" = false ]; then
        read -p "This will update nPanel to the latest version. Continue? (y/n) " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            print_warning "Update cancelled"
            exit 0
        fi
    else
        print_status "Auto-yes mode: Skipping confirmation"
    fi
    
    # Perform update steps
    create_backup
    enable_maintenance_mode
    stop_queue_workers
    
    # Try to update, rollback on failure
    if pull_latest_code && \
       update_dependencies && \
       build_assets && \
       run_migrations && \
       check_env_variables && \
       clear_caches && \
       optimize_laravel && \
       update_permissions && \
       check_mail_server_update && \
       restart_services; then
        
        start_queue_workers
        disable_maintenance_mode
        print_update_info
        print_success "Update completed successfully!"
    else
        print_error "Update failed! Maintenance mode is still enabled."
        rollback_instructions
        exit 1
    fi
}

# Run main function
main
