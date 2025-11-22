#!/bin/bash

###############################################################################
# Reset Mailbox Password
###############################################################################

if [ -z "$1" ] || [ -z "$2" ]; then
    echo "Usage: $0 <email> <new_password>"
    echo "Example: $0 admin@npanel.at MyNewPassword123"
    exit 1
fi

EMAIL="$1"
PASSWORD="$2"

echo "=== Resetting password for $EMAIL ==="

# Generate SHA512-CRYPT hash
HASH=$(php -r "echo crypt('$PASSWORD', '\\\$6\\\$rounds=5000\\\$' . substr(base64_encode(random_bytes(16)), 0, 16) . '\\\$');")

echo "Generated password hash: ${HASH:0:30}..."

# Update in MySQL (mail database) - This is what Dovecot uses
mysql -u npanel_mail -pHckaj6MoTbw2fma3lyE04N3Uu npanel_mail -e "UPDATE mailboxes SET password_encrypted='$HASH', updated_at=NOW() WHERE email='$EMAIL';"

if [ $? -eq 0 ]; then
    echo "✓ Updated in MySQL (mail database)"
else
    echo "✗ Failed to update MySQL"
    exit 1
fi

# Also sync to Laravel database via artisan command
cd /var/www/npanel
php artisan mail:sync-database > /dev/null 2>&1 || echo "⚠ Could not sync to Laravel database (non-critical)"

echo ""
echo "=== Password Reset Complete ==="
echo ""
echo "Testing authentication via Dovecot SQL query..."

# Test if password matches
TEST_RESULT=$(mysql -u npanel_mail -pHckaj6MoTbw2fma3lyE04N3Uu npanel_mail -sN -e "SELECT COUNT(*) FROM mailboxes WHERE email='$EMAIL' AND password_encrypted='$HASH' AND status='active';")

if [ "$TEST_RESULT" = "1" ]; then
    echo "✓ Password successfully stored in database"
else
    echo "✗ Warning: Password not found in database (might be encryption issue)"
fi

echo ""
echo "Try Roundcube login:"
echo "  https://webmail.npanel.at"
echo "  Email: $EMAIL"
echo "  Password: $PASSWORD"
echo ""
echo "If login fails, check Dovecot logs:"
echo "  journalctl -u dovecot --no-pager | tail -20"
