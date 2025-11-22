#!/bin/bash

###############################################################################
# Enable Postfix Submission Port (587) for Authenticated SMTP
###############################################################################

set -e

echo "=== Enabling Postfix Submission Port (587) ==="

# Backup master.cf
cp /etc/postfix/master.cf /etc/postfix/master.cf.backup.$(date +%s)

# Check if submission is already enabled
if grep -q "^submission inet" /etc/postfix/master.cf; then
    echo "✓ Submission port already enabled"
    exit 0
fi

# Enable submission port and its options
cat >> /etc/postfix/master.cf << 'EOF'

# Submission port for authenticated SMTP (port 587)
submission inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_tls_auth_only=yes
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
  -o smtpd_sender_login_maps=mysql:/etc/postfix/mysql/virtual-sender-login-maps.cf
  -o smtpd_sender_restrictions=reject_sender_login_mismatch
  -o smtpd_recipient_restrictions=reject_non_fqdn_recipient,reject_unknown_recipient_domain,permit_sasl_authenticated,reject
EOF

# Create sender login maps if not exists
if [ ! -f /etc/postfix/mysql/virtual-sender-login-maps.cf ]; then
    mkdir -p /etc/postfix/mysql
    cat > /etc/postfix/mysql/virtual-sender-login-maps.cf << EOF
user = $(grep MAIL_DB_USERNAME /var/www/npanel/.env | cut -d '=' -f2)
password = $(grep MAIL_DB_PASSWORD /var/www/npanel/.env | cut -d '=' -f2)
hosts = 127.0.0.1
dbname = $(grep MAIL_DB_DATABASE /var/www/npanel/.env | cut -d '=' -f2)
query = SELECT email FROM mailboxes WHERE email='%s' AND status='active'
EOF
    chmod 640 /etc/postfix/mysql/virtual-sender-login-maps.cf
    chown root:postfix /etc/postfix/mysql/virtual-sender-login-maps.cf
    echo "✓ Created sender login maps"
fi

# Enable SASL authentication in main.cf
postconf -e "smtpd_sasl_type=dovecot"
postconf -e "smtpd_sasl_path=private/auth"
postconf -e "smtpd_sasl_auth_enable=yes"

# Test configuration
echo "Testing Postfix configuration..."
postfix check

if [ $? -eq 0 ]; then
    echo "✓ Configuration valid"
    
    # Reload Postfix
    systemctl reload postfix
    echo "✓ Postfix reloaded"
    
    # Show listening ports
    echo ""
    echo "=== Postfix listening ports ==="
    netstat -tlnp | grep master
    
    echo ""
    echo "=== Submission port enabled successfully ==="
    echo "Roundcube can now send emails via port 587"
else
    echo "✗ Configuration test failed"
    echo "Restoring backup..."
    cp /etc/postfix/master.cf.backup.$(date +%s) /etc/postfix/master.cf
    exit 1
fi
