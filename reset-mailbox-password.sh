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

# Update in SQLite (main database)
sqlite3 /var/www/npanel/database/database.sqlite "UPDATE mailboxes SET password_encrypted='$HASH', updated_at=CURRENT_TIMESTAMP WHERE email='$EMAIL';"

if [ $? -eq 0 ]; then
    echo "✓ Updated in SQLite"
else
    echo "✗ Failed to update SQLite"
    exit 1
fi

# Update in MySQL (mail database)
mysql -u npanel_mail -pHckaj6MoTbw2fma3lyE04N3Uu npanel_mail -e "UPDATE mailboxes SET password_encrypted='$HASH', updated_at=NOW() WHERE email='$EMAIL';"

if [ $? -eq 0 ]; then
    echo "✓ Updated in MySQL"
else
    echo "✗ Failed to update MySQL"
    exit 1
fi

echo ""
echo "=== Password Reset Complete ==="
echo ""
echo "Test authentication:"
echo "  doveadm auth test $EMAIL $PASSWORD"
echo ""
echo "Try Roundcube login:"
echo "  https://webmail.npanel.at"
echo "  Email: $EMAIL"
echo "  Password: $PASSWORD"
