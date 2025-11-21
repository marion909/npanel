#!/bin/bash

###############################################################################
# nPanel Update Script
# Automated update script for existing nPanel installations
###############################################################################

set -e  # Exit on error

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
        
        # Wait for user to see credentials
        read -p "Press Enter to continue after saving these credentials..."
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
    
    # Confirm update
    read -p "This will update nPanel to the latest version. Continue? (y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_warning "Update cancelled"
        exit 0
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
