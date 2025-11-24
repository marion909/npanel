#!/bin/bash

# NPanel Deployment/Update Script
# Usage: ./deploy.sh [--first-time]

set -e

echo "=========================================="
echo "NPanel Deployment Script"
echo "=========================================="

FIRST_TIME=false
if [[ "$1" == "--first-time" ]]; then
    FIRST_TIME=true
    echo "Running FIRST TIME deployment..."
fi

# First-time system setup
if [ "$FIRST_TIME" = true ]; then
    echo ">>> Installing system prerequisites..."
    
    # Update system
    echo "Updating system packages..."
    sudo apt update && sudo apt upgrade -y
    
    # Install basic tools first
    echo "Installing basic tools..."
    sudo apt install -y git unzip curl wget gnupg2 ca-certificates lsb-release apt-transport-https
    
    # Add Sury PHP repository (works on Debian and Ubuntu)
    echo "Adding PHP repository..."
    sudo curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
    sudo sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
    sudo apt update
    
    # Install base packages
    echo "Installing Nginx, MariaDB, PHP 8.2 and extensions..."
    sudo apt install -y nginx mariadb-server \
        php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml \
        php8.2-curl php8.2-zip php8.2-bcmath php8.2-tokenizer
    
    # Install Node.js 18+
    if ! command -v node &> /dev/null; then
        echo "Installing Node.js..."
        curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
        sudo apt install -y nodejs
    fi
    
    # Install Composer
    if ! command -v composer &> /dev/null; then
        echo "Installing Composer..."
        curl -sS https://getcomposer.org/installer | php
        sudo mv composer.phar /usr/local/bin/composer
        sudo chmod +x /usr/local/bin/composer
    else
        # Move local composer.phar if it exists
        if [ -f composer.phar ]; then
            sudo mv composer.phar /usr/local/bin/composer
            sudo chmod +x /usr/local/bin/composer
        fi
    fi
    
    # Install Certbot with DNS Hetzner plugin
    echo "Installing Certbot and Python pip..."
    sudo apt install -y certbot python3-certbot-nginx python3-pip
    
    echo "Installing certbot-dns-hetzner via pip..."
    sudo pip3 install certbot-dns-hetzner --break-system-packages
    
    # Secure MariaDB installation
    echo ""
    echo ">>> Please run 'sudo mysql_secure_installation' manually after this script."
    echo ">>> Then create the database with:"
    echo "    sudo mysql -u root -p"
    echo "    CREATE DATABASE npanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo "    CREATE USER 'npanel'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD';"
    echo "    GRANT ALL PRIVILEGES ON npanel.* TO 'npanel'@'localhost';"
    echo "    FLUSH PRIVILEGES;"
    echo "    EXIT;"
    echo ""
    
    # Start services
    echo "Starting services..."
    sudo systemctl enable nginx
    sudo systemctl enable mariadb
    sudo systemctl enable php8.2-fpm
    sudo systemctl start nginx
    sudo systemctl start mariadb
    sudo systemctl start php8.2-fpm
    
    echo ">>> System prerequisites installed!"
    echo ""
fi

# Check if .env exists
if [ ! -f .env ]; then
    echo "Error: .env file not found!"
    echo "Copy .env.example to .env and configure it first."
    exit 1
fi

# Enable maintenance mode
echo ">>> Enabling maintenance mode..."
php artisan down || true

# Pull latest code
echo ">>> Pulling latest code from git..."
git pull origin main

# Install/Update Composer dependencies
echo ">>> Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install/Update NPM dependencies
echo ">>> Installing NPM dependencies..."
npm ci --legacy-peer-deps

# Build frontend assets
echo ">>> Building frontend assets..."
npm run build

# Run migrations
echo ">>> Running database migrations..."
php artisan migrate --force

# Clear and cache config
echo ">>> Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# First-time specific setup
if [ "$FIRST_TIME" = true ]; then
    echo ">>> Running first-time setup..."
    
    # Generate app key if needed
    if grep -q "APP_KEY=$" .env; then
        php artisan key:generate --force
    fi
    
    # Create base directories
    php artisan system:install
    
    # Create storage link
    php artisan storage:link
    
    # Scan Hetzner zones
    if grep -q "HETZNER_DNS_API_TOKEN=.\\+" .env; then
        echo ">>> Scanning Hetzner DNS zones..."
        php artisan hetzner:zone-scan
    fi
    
    echo ""
    echo ">>> First-time setup complete!"
    echo ">>> Don't forget to:"
    echo "    1. Run 'sudo mysql_secure_installation' if not done yet"
    echo "    2. Create MySQL database and user (see instructions above)"
    echo "    3. Configure your Nginx virtual host:"
    echo "       sudo nano /etc/nginx/sites-available/npanel"
    echo "       sudo ln -s /etc/nginx/sites-available/npanel /etc/nginx/sites-enabled/"
    echo "       sudo nginx -t && sudo systemctl reload nginx"
    echo "    4. Set up SSL with Certbot:"
    echo "       sudo certbot --nginx -d your-domain.com"
    echo "    5. Configure cron for scheduled tasks:"
    echo "       crontab -e"
    echo "       * * * * * cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1"
    echo ""
    echo "    Nginx example config:"
    echo "    server {"
    echo "        listen 80;"
    echo "        server_name your-domain.com;"
    echo "        root $(pwd)/public;"
    echo "        index index.php;"
    echo ""
    echo "        location / {"
    echo "            try_files \$uri \$uri/ /index.php?\$query_string;"
    echo "        }"
    echo ""
    echo "        location ~ \.php$ {"
    echo "            include fastcgi_params;"
    echo "            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;"
    echo "            fastcgi_pass unix:/run/php/php8.2-fpm.sock;"
    echo "        }"
    echo ""
    echo "        location ~ /\.(?!well-known).* {"
    echo "            deny all;"
    echo "        }"
    echo "    }"
fi

# Set proper permissions
echo ">>> Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Restart services if available
if systemctl is-active --quiet php8.2-fpm; then
    echo ">>> Restarting PHP-FPM..."
    sudo systemctl restart php8.2-fpm
fi

if systemctl is-active --quiet nginx; then
    echo ">>> Reloading Nginx..."
    sudo nginx -t && sudo systemctl reload nginx
fi

# Disable maintenance mode
echo ">>> Disabling maintenance mode..."
php artisan up

echo ""
echo "=========================================="
echo "Deployment completed successfully!"
echo "=========================================="
echo ""

# Show application info
php artisan about
