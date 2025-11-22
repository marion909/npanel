#!/bin/bash

# Setup Mail Server Database for nPanel
# This script creates a dedicated MySQL database for Postfix/Dovecot

set -e

echo "=== nPanel Mail Server Database Setup ==="
echo ""

# Configuration
MAIL_DB_NAME="npanel_mail"
MAIL_DB_USER="npanel_mail"
MAIL_DB_PASS=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-25)

# Get MySQL root password
read -sp "Enter MySQL root password: " MYSQL_ROOT_PASS
echo ""

# Create database
echo "Creating database ${MAIL_DB_NAME}..."
mysql -u root -p"${MYSQL_ROOT_PASS}" <<EOF
CREATE DATABASE IF NOT EXISTS ${MAIL_DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${MAIL_DB_USER}'@'localhost' IDENTIFIED BY '${MAIL_DB_PASS}';
GRANT ALL PRIVILEGES ON ${MAIL_DB_NAME}.* TO '${MAIL_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

echo "Database created successfully!"
echo ""
echo "=== Add these to your .env file: ==="
echo "MAIL_DB_HOST=127.0.0.1"
echo "MAIL_DB_PORT=3306"
echo "MAIL_DB_DATABASE=${MAIL_DB_NAME}"
echo "MAIL_DB_USERNAME=${MAIL_DB_USER}"
echo "MAIL_DB_PASSWORD=${MAIL_DB_PASS}"
echo ""

# Create tables for mail server
echo "Creating mail server tables..."
mysql -u "${MAIL_DB_USER}" -p"${MAIL_DB_PASS}" "${MAIL_DB_NAME}" <<'EOF'

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

echo "Tables created successfully!"
echo ""
echo "=== Mail database setup completed! ==="
echo ""
echo "Next steps:"
echo "1. Update your .env file with the credentials shown above"
echo "2. Run: php artisan config:clear"
echo "3. Run: php artisan mail:install (or similar command to configure Postfix/Dovecot)"
echo ""
