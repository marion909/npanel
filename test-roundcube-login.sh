#!/bin/bash

###############################################################################
# Test Roundcube Login
###############################################################################

echo "=== Roundcube Login Test ==="
echo ""

# Test 1: Check Roundcube config
echo "1. Checking Roundcube config..."
if grep -q "localhost:143" /var/www/roundcube/config/config.inc.php; then
    echo "✓ Roundcube IMAP config correct (localhost:143)"
else
    echo "✗ Roundcube IMAP config wrong"
fi

if grep -q "localhost:587" /var/www/roundcube/config/config.inc.php; then
    echo "✓ Roundcube SMTP config correct (localhost:587)"
else
    echo "✗ Roundcube SMTP config wrong"
fi

echo ""

# Test 2: Check Dovecot
echo "2. Checking Dovecot..."
if systemctl is-active --quiet dovecot; then
    echo "✓ Dovecot is running"
else
    echo "✗ Dovecot is NOT running"
    exit 1
fi

# Test 3: Check Postfix
echo "3. Checking Postfix..."
if systemctl is-active --quiet postfix; then
    echo "✓ Postfix is running"
else
    echo "✗ Postfix is NOT running"
fi

echo ""

# Test 4: Check mailboxes in database
echo "4. Checking mailboxes..."
MAILBOXES=$(mysql -u npanel_mail -pHckaj6MoTbw2fma3lyE04N3Uu npanel_mail -sN -e "SELECT COUNT(*) FROM mailboxes WHERE status='active'")
echo "Found $MAILBOXES active mailbox(es)"

if [ "$MAILBOXES" -gt 0 ]; then
    echo ""
    echo "Available mailboxes:"
    mysql -u npanel_mail -pHckaj6MoTbw2fma3lyE04N3Uu npanel_mail -e "SELECT email, status, created_at FROM mailboxes"
fi

echo ""

# Test 5: Try Dovecot auth
echo "5. Testing Dovecot authentication..."
echo "Enter mailbox email (e.g., admin@npanel.at):"
read MAILBOX_EMAIL

echo "Enter mailbox password:"
read -s MAILBOX_PASS

echo ""
echo "Testing auth with doveadm..."
if doveadm auth test "$MAILBOX_EMAIL" "$MAILBOX_PASS" 2>&1 | grep -q "auth succeeded"; then
    echo "✓ Authentication successful!"
else
    echo "✗ Authentication failed"
    echo "Check password or run: journalctl -u dovecot | tail -20"
fi

echo ""
echo "=== Test Complete ==="
echo ""
echo "Next steps:"
echo "1. Open: https://webmail.npanel.at (or your webmail subdomain)"
echo "2. Login with: $MAILBOX_EMAIL"
echo "3. Use the password you entered above"
echo ""
echo "If login fails, check logs:"
echo "  tail -f /var/log/mail.log"
echo "  journalctl -fu dovecot"
