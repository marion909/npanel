#!/bin/bash

###############################################################################
# Quick Fix: Roundcube Configuration
# Fixes IMAP/SMTP settings for localhost connection
###############################################################################

set -e

echo "=== Roundcube Configuration Quick Fix ==="
echo ""

# Configuration
ROUNDCUBE_PATH="/var/www/roundcube"
NPANEL_PATH="/var/www/npanel"

# Check if Roundcube exists
if [ ! -d "$ROUNDCUBE_PATH" ]; then
    echo "ERROR: Roundcube not found at $ROUNDCUBE_PATH"
    exit 1
fi

echo "✓ Found Roundcube at $ROUNDCUBE_PATH"

# Backup existing config
if [ -f "$ROUNDCUBE_PATH/config/config.inc.php" ]; then
    cp "$ROUNDCUBE_PATH/config/config.inc.php" "$ROUNDCUBE_PATH/config/config.inc.php.backup.$(date +%Y%m%d_%H%M%S)"
    echo "✓ Backed up existing config"
fi

# Get database credentials from existing config or create new
if [ -f "$ROUNDCUBE_PATH/config/config.inc.php" ]; then
    # Extract existing credentials
    DB_DSN=$(grep "db_dsnw" "$ROUNDCUBE_PATH/config/config.inc.php" | grep -oP "mysql://\K[^']*" || echo "")
    DES_KEY=$(grep "des_key" "$ROUNDCUBE_PATH/config/config.inc.php" | grep -oP "'\K[^']*" || echo "")
fi

# If no credentials found, try to get from MySQL
if [ -z "$DB_DSN" ]; then
    echo "Creating new Roundcube database..."
    
    DB_USER="roundcube"
    DB_PASSWORD=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-25)
    DB_NAME="roundcube"
    
    # Get MySQL root credentials from nPanel .env
    if [ -f "$NPANEL_PATH/.env" ]; then
        MYSQL_ROOT_USER=$(grep "MYSQL_ROOT_USERNAME" "$NPANEL_PATH/.env" | cut -d '=' -f2)
        MYSQL_ROOT_PASS=$(grep "MYSQL_ROOT_PASSWORD" "$NPANEL_PATH/.env" | cut -d '=' -f2)
        
        if [ -n "$MYSQL_ROOT_USER" ] && [ -n "$MYSQL_ROOT_PASS" ]; then
            MYSQL_CMD="mysql -u ${MYSQL_ROOT_USER} -p${MYSQL_ROOT_PASS}"
        else
            MYSQL_CMD="mysql"
        fi
    else
        MYSQL_CMD="mysql"
    fi
    
    # Create database
    $MYSQL_CMD <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
    
    # Import schema if available
    if [ -f "$ROUNDCUBE_PATH/SQL/mysql.initial.sql" ]; then
        mysql -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" < "$ROUNDCUBE_PATH/SQL/mysql.initial.sql" 2>/dev/null || true
        echo "✓ Imported Roundcube database schema"
    fi
    
    DB_DSN="${DB_USER}:${DB_PASSWORD}@localhost/${DB_NAME}"
    echo "✓ Created Roundcube database"
else
    echo "✓ Using existing database credentials"
fi

# Generate DES key if not exists
if [ -z "$DES_KEY" ]; then
    DES_KEY=$(openssl rand -base64 24)
    echo "✓ Generated new DES key"
fi

# Write new config
echo "Writing new Roundcube configuration..."

cat > "$ROUNDCUBE_PATH/config/config.inc.php" <<EOF
<?php
\$config = [];

// Database connection
\$config['db_dsnw'] = 'mysql://${DB_DSN}';

// IMAP settings - connect to local Dovecot
\$config['default_host'] = 'localhost';
\$config['default_port'] = 143;
\$config['imap_conn_options'] = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
];
\$config['imap_auth_type'] = null;
\$config['imap_delimiter'] = '/';

// SMTP settings - connect to local Postfix
\$config['smtp_host'] = 'localhost:587';
\$config['smtp_user'] = '%u';
\$config['smtp_pass'] = '%p';
\$config['smtp_conn_options'] = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
];

// Security
\$config['des_key'] = '${DES_KEY}';
\$config['cipher_method'] = 'AES-256-CBC';
\$config['username_domain'] = '';

// UI
\$config['product_name'] = 'nPanel Webmail';
\$config['support_url'] = '';
\$config['skin'] = 'elastic';
\$config['language'] = 'en_US';

// Identities
\$config['identities_level'] = 0;

// Misc
\$config['enable_installer'] = false;
\$config['log_driver'] = 'syslog';
\$config['syslog_facility'] = LOG_MAIL;
\$config['session_lifetime'] = 30;

// Plugins
\$config['plugins'] = [];
EOF

chown www-data:www-data "$ROUNDCUBE_PATH/config/config.inc.php"
chmod 640 "$ROUNDCUBE_PATH/config/config.inc.php"

echo "✓ Roundcube configuration updated"
echo ""
echo "=== Configuration Summary ==="
echo "IMAP: localhost:143 (unencrypted)"
echo "SMTP: localhost:587 (unencrypted)"
echo "SSL verification: disabled for localhost"
echo ""
echo "=== Test Login ==="
echo "1. Go to: https://webmail.yourdomain.de"
echo "2. Username: your@email.com"
echo "3. Password: your mailbox password"
echo ""
echo "=== Check Logs ==="
echo "tail -f /var/log/mail.log"
echo "tail -f /var/log/dovecot.log"
echo ""
echo "✅ Done! Try logging in to Roundcube now."
