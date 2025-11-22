# Mail Server Setup Guide for nPanel

## Problem Analysis

The mail server (Postfix/Dovecot) was not working because:

1. **Database Configuration Mismatch**: The Laravel application uses SQLite (`DB_CONNECTION=sqlite`), but Postfix and Dovecot require MySQL for virtual mailbox lookups
2. **Missing MySQL Credentials**: The mail services tried to read from `config('database.connections.mysql.*')` which was empty
3. **No Dedicated Mail Database**: Mail data needs to be in MySQL, not SQLite

## Solution

We've implemented **separate database connections**:
- **Laravel Application**: Uses SQLite (as configured)
- **Mail Server (Postfix/Dovecot)**: Uses dedicated MySQL database with `MAIL_DB_*` environment variables

## Setup Instructions

### Step 1: Setup Mail Database on Server

SSH into your server (49.13.168.95) and run:

```bash
cd /path/to/npanel
chmod +x setup-mail-database.sh
./setup-mail-database.sh
```

This script will:
- Create a new MySQL database `npanel_mail`
- Create a dedicated user `npanel_mail` with a secure random password
- Create the necessary tables (domains, mailboxes, mail_aliases)
- Display credentials to add to your `.env`

### Step 2: Update .env on Server

Add the generated credentials to your `.env` file:

```env
# Mail Server Database Configuration (for Postfix/Dovecot)
MAIL_DB_HOST=127.0.0.1
MAIL_DB_PORT=3306
MAIL_DB_DATABASE=npanel_mail
MAIL_DB_USERNAME=npanel_mail
MAIL_DB_PASSWORD=<generated_password_from_script>
```

**Important**: Keep your existing Laravel database configuration:
```env
DB_CONNECTION=sqlite
# ... your SQLite config ...
```

### Step 3: Sync Data Between Databases

You need to sync domain data from SQLite to MySQL. Create and run this artisan command:

```bash
php artisan make:command SyncMailDatabase
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncMailDatabase extends Command
{
    protected $signature = 'mail:sync-database';
    protected $description = 'Sync domain data from SQLite to Mail MySQL database';

    public function handle()
    {
        // Get domains from SQLite
        $domains = DB::table('domains')->get();

        // Connect to mail database
        $mailDb = DB::connection('mail');

        foreach ($domains as $domain) {
            // Insert or update domain in mail database
            $mailDb->table('domains')->updateOrInsert(
                ['domain_name' => $domain->domain_name],
                [
                    'id' => $domain->id,
                    'user_id' => $domain->user_id,
                    'status' => $domain->status,
                    'created_at' => $domain->created_at,
                    'updated_at' => $domain->updated_at,
                ]
            );
        }

        // Sync mailboxes
        $mailboxes = DB::table('mailboxes')->get();
        foreach ($mailboxes as $mailbox) {
            $mailDb->table('mailboxes')->updateOrInsert(
                ['email' => $mailbox->email],
                [
                    'id' => $mailbox->id,
                    'domain_id' => $mailbox->domain_id,
                    'password_encrypted' => $mailbox->password_encrypted,
                    'quota_mb' => $mailbox->quota_mb,
                    'used_mb' => $mailbox->used_mb,
                    'status' => $mailbox->status,
                    'created_at' => $mailbox->created_at,
                    'updated_at' => $mailbox->updated_at,
                ]
            );
        }

        // Sync aliases
        $aliases = DB::table('mail_aliases')->get();
        foreach ($aliases as $alias) {
            $mailDb->table('mail_aliases')->updateOrInsert(
                ['id' => $alias->id],
                (array) $alias
            );
        }

        $this->info('Mail database synced successfully!');
        $this->info("Domains: {$domains->count()}");
        $this->info("Mailboxes: {$mailboxes->count()}");
        $this->info("Aliases: {$aliases->count()}");
    }
}
```

Run the sync:
```bash
php artisan mail:sync-database
```

### Step 4: Configure Mail Database Connection

Add to `config/database.php`:

```php
'connections' => [
    // ... existing connections ...

    'mail' => [
        'driver' => 'mysql',
        'host' => env('MAIL_DB_HOST', '127.0.0.1'),
        'port' => env('MAIL_DB_PORT', '3306'),
        'database' => env('MAIL_DB_DATABASE', 'npanel_mail'),
        'username' => env('MAIL_DB_USERNAME', 'npanel_mail'),
        'password' => env('MAIL_DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

### Step 5: Update Models to Use Mail Database

For mail-related operations, models should use the mail database:

```php
// In Domain, Mailbox, MailAlias models when performing mail-server operations
protected $connection = 'mail'; // Add this only when needed for mail operations
```

**Alternative approach (better)**: Keep models on SQLite and sync data when mail operations happen.

### Step 6: Regenerate Postfix/Dovecot Configs

```bash
php artisan config:clear
php artisan cache:clear

# Regenerate mail server configs (create this command if needed)
php artisan mail:regenerate-configs
```

### Step 7: Restart Mail Services

```bash
sudo systemctl restart postfix
sudo systemctl restart dovecot
```

### Step 8: Test Mail Server

```bash
# Check Postfix can query virtual domains
postmap -q example.com mysql:/etc/postfix/mysql/virtual-domains.cf

# Check Postfix can query mailboxes
postmap -q user@example.com mysql:/etc/postfix/mysql/virtual-mailboxes.cf

# Check Dovecot auth
doveadm auth test user@example.com password

# Check logs
tail -f /var/log/mail.log
```

## Architecture Changes Made

### 1. PostfixService.php
- Changed `getDatabaseConfig()` to use `env('MAIL_DB_*')` instead of `config('database.connections.mysql.*')`

### 2. DovecotService.php
- Changed `getDatabaseConfig()` to use `env('MAIL_DB_*')` instead of `config('database.connections.mysql.*')`

### 3. .env
- Added new section with `MAIL_DB_*` environment variables

## Ongoing Sync Strategy

**Option A: Sync on Write** (Recommended)
- When creating/updating domains, mailboxes, aliases: write to both SQLite and MySQL
- Modify service methods to use dual writes

**Option B: Periodic Sync**
- Run `php artisan mail:sync-database` via cron every 5 minutes
- Add to crontab: `*/5 * * * * cd /path/to/npanel && php artisan mail:sync-database`

**Option C: Database Triggers** (Advanced)
- Set up MySQL triggers to sync changes back to SQLite (complex)

## Troubleshooting

### Postfix can't connect to MySQL
```bash
# Check MySQL connection
mysql -u npanel_mail -p npanel_mail
# Enter password from .env

# Check Postfix config files
cat /etc/postfix/mysql/virtual-domains.cf

# Test query manually
mysql -u npanel_mail -p -e "SELECT domain_name FROM domains WHERE domain_name='example.com' AND status='active'" npanel_mail
```

### Dovecot authentication fails
```bash
# Check dovecot-sql.conf.ext
cat /etc/dovecot/dovecot-sql.conf.ext

# Test SQL query
mysql -u npanel_mail -p -e "SELECT email, password_encrypted FROM mailboxes WHERE email='user@example.com' AND status='active'" npanel_mail

# Check Dovecot logs
tail -f /var/log/dovecot.log
```

### Services won't reload
```bash
# Test configs
postfix check
doveconf -n

# Check for syntax errors
journalctl -u postfix -n 50
journalctl -u dovecot -n 50
```

## Next Steps

1. ✅ Updated PostfixService and DovecotService to use dedicated mail database ENV variables
2. ✅ Created setup script for mail database
3. ⬜ Add `mail` connection to `config/database.php`
4. ⬜ Create sync command or implement dual-write strategy
5. ⬜ Test mail server functionality
6. ⬜ Set up automatic sync (cron or event listeners)
