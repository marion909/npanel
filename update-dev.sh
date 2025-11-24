#!/bin/bash

# NPanel Development Update Script (Linux/Mac)
# Usage: ./update-dev.sh

set -e

echo "=========================================="
echo "NPanel Development Update"
echo "=========================================="
echo ""

# Pull latest code
echo ">>> Pulling latest code..."
git pull origin main

# Install/Update Composer dependencies (with dev)
echo ">>> Installing Composer dependencies..."
composer install --no-interaction

# Install/Update NPM dependencies
echo ">>> Installing NPM dependencies..."
npm install --legacy-peer-deps

# Build frontend assets
echo ">>> Building frontend assets..."
npm run build

# Run migrations
echo ">>> Running database migrations..."
php artisan migrate

# Clear caches (development)
echo ">>> Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo ""
echo "=========================================="
echo "Development update completed!"
echo "=========================================="
echo ""

php artisan about
