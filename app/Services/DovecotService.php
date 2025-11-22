<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Exception;

class DovecotService
{
    private string $configPath = '/etc/dovecot';

    /**
     * Generate Dovecot SQL authentication configuration.
     *
     * @return void
     * @throws Exception
     */
    public function generateSqlConfig(): void
    {
        $dbConfig = $this->getDatabaseConfig();

        $content = <<<EOF
driver = mysql
connect = host={$dbConfig['host']} dbname={$dbConfig['database']} user={$dbConfig['username']} password={$dbConfig['password']}
default_pass_scheme = SHA512-CRYPT

# Password query
password_query = SELECT email as user, password_encrypted as password FROM mailboxes WHERE email='%u' AND status='active'

# User query for IMAP/POP3
user_query = SELECT CONCAT('/var/vmail/', SUBSTRING_INDEX(email, '@', -1), '/', SUBSTRING_INDEX(email, '@', 1)) as home, 5000 as uid, 5000 as gid FROM mailboxes WHERE email='%u' AND status='active'

# Iterate query for doveadm user listing
iterate_query = SELECT email as user FROM mailboxes WHERE status='active'

EOF;

        File::put("{$this->configPath}/dovecot-sql.conf.ext", $content);
        
        // Set secure permissions (root:root 600)
        Process::run("chmod 600 {$this->configPath}/dovecot-sql.conf.ext");
        Process::run("chown root:root {$this->configPath}/dovecot-sql.conf.ext");

        Log::info("Generated dovecot-sql.conf.ext");
    }

    /**
     * Update 10-auth.conf to use SQL authentication.
     *
     * @return void
     * @throws Exception
     */
    public function updateAuthConfig(): void
    {
        $authConfigPath = "{$this->configPath}/conf.d/10-auth.conf";

        if (!File::exists($authConfigPath)) {
            throw new Exception("Dovecot 10-auth.conf not found");
        }

        // Backup current config
        File::copy($authConfigPath, "{$authConfigPath}.backup." . time());

        $content = File::get($authConfigPath);

        // Disable system auth
        $content = preg_replace('/^!include auth-system\.conf\.ext$/m', '#!include auth-system.conf.ext', $content);
        
        // Disable dict auth (can conflict with SQL auth)
        $content = preg_replace('/^!include auth-dict\.conf\.ext$/m', '#!include auth-dict.conf.ext', $content);
        
        // Enable SQL auth if not already enabled
        if (!preg_match('/^!include auth-sql\.conf\.ext$/m', $content)) {
            $content = preg_replace('/#!include auth-sql\.conf\.ext/m', '!include auth-sql.conf.ext', $content);
        }

        File::put($authConfigPath, $content);
        Log::info("Updated 10-auth.conf to use SQL authentication");
    }

    /**
     * Update 10-mail.conf with Maildir location and vmail user.
     *
     * @return void
     * @throws Exception
     */
    public function updateMailConfig(): void
    {
        $mailConfigPath = "{$this->configPath}/conf.d/10-mail.conf";

        if (!File::exists($mailConfigPath)) {
            throw new Exception("Dovecot 10-mail.conf not found");
        }

        // Backup current config
        File::copy($mailConfigPath, "{$mailConfigPath}.backup." . time());

        $content = File::get($mailConfigPath);

        // Set mail_location to Maildir
        $content = preg_replace(
            '/^#?mail_location = .*$/m',
            'mail_location = maildir:/var/vmail/%d/%n',
            $content
        );

        // Set mail_privileged_group
        $content = preg_replace(
            '/^#?mail_privileged_group = .*$/m',
            'mail_privileged_group = vmail',
            $content
        );

        // Set first_valid_uid and last_valid_uid for vmail (5000)
        if (!preg_match('/^first_valid_uid = 5000$/m', $content)) {
            $content .= "\nfirst_valid_uid = 5000\nlast_valid_uid = 5000\n";
        }

        File::put($mailConfigPath, $content);
        Log::info("Updated 10-mail.conf with Maildir location");
    }

    /**
     * Update 10-master.conf with LMTP and auth sockets.
     *
     * @return void
     * @throws Exception
     */
    public function updateMasterConfig(): void
    {
        $masterConfigPath = "{$this->configPath}/conf.d/10-master.conf";

        if (!File::exists($masterConfigPath)) {
            throw new Exception("Dovecot 10-master.conf not found");
        }

        // Backup current config
        File::copy($masterConfigPath, "{$masterConfigPath}.backup." . time());

        $lmtpConfig = <<<EOF

# nPanel LMTP Configuration
service lmtp {
  unix_listener /var/spool/postfix/private/dovecot-lmtp {
    mode = 0600
    user = postfix
    group = postfix
  }
}

# Postfix SMTP Auth Socket
service auth {
  unix_listener /var/spool/postfix/private/auth {
    mode = 0660
    user = postfix
    group = postfix
  }
}

EOF;

        $content = File::get($masterConfigPath);

        // Append LMTP config if not already present
        if (!str_contains($content, 'nPanel LMTP Configuration')) {
            File::append($masterConfigPath, $lmtpConfig);
            Log::info("Updated 10-master.conf with LMTP and auth sockets");
        } else {
            Log::info("10-master.conf already contains LMTP configuration");
        }
    }

    /**
     * Update auth-sql.conf.ext with inline SQL configuration (Dovecot 2.4+ format).
     *
     * @return void
     * @throws Exception
     */
    public function updateSqlAuthConfig(): void
    {
        $sqlAuthConfigPath = "{$this->configPath}/conf.d/auth-sql.conf.ext";

        if (!File::exists($sqlAuthConfigPath)) {
            throw new Exception("Dovecot auth-sql.conf.ext not found");
        }

        // Backup current config
        File::copy($sqlAuthConfigPath, "{$sqlAuthConfigPath}.backup." . time());

        $dbConfig = $this->getDatabaseConfig();

        // Dovecot 2.4+ requires inline configuration with sql_driver and mysql block
        $content = <<<EOF
# nPanel SQL Authentication
# https://doc.dovecot.org/latest/core/config/auth/databases/sql.html

sql_driver = mysql

mysql localhost {
  user = {$dbConfig['username']}
  password = {$dbConfig['password']}
  dbname = {$dbConfig['database']}
}

passdb sql {
  query = SELECT email as user, password_encrypted as password FROM mailboxes WHERE email='%{user}' AND status='active'
}

userdb sql {
  query = SELECT '/var/vmail' as home, 5000 as uid, 5000 as gid FROM mailboxes WHERE email='%{user}' AND status='active'
  iterate_query = SELECT email as user FROM mailboxes WHERE status='active'
}

EOF;

        File::put($sqlAuthConfigPath, $content);
        
        // Set secure permissions (root:root 600)
        Process::run("chmod 600 {$sqlAuthConfigPath}");
        Process::run("chown root:root {$sqlAuthConfigPath}");
        
        Log::info("Updated auth-sql.conf.ext with Dovecot 2.4+ inline configuration");
    }

    /**
     * Test Dovecot configuration.
     *
     * @return bool
     */
    public function testConfig(): bool
    {
        $result = Process::run('doveconf -n');
        
        if (!$result->successful()) {
            Log::error("Dovecot config test failed: " . $result->errorOutput());
            return false;
        }

        return true;
    }

    /**
     * Reload Dovecot service.
     *
     * @return void
     * @throws Exception
     */
    public function reload(): void
    {
        if (!$this->testConfig()) {
            throw new Exception("Dovecot configuration test failed. Not reloading.");
        }

        $result = Process::run('systemctl reload dovecot');

        if (!$result->successful()) {
            Log::error("Failed to reload Dovecot: " . $result->errorOutput());
            throw new Exception("Failed to reload Dovecot service.");
        }

        Log::info("Dovecot reloaded successfully");
    }

    /**
     * Get database configuration for mail server (Postfix/Dovecot).
     * Uses dedicated MAIL_DB_* environment variables.
     *
     * @return array
     */
    private function getDatabaseConfig(): array
    {
        return [
            'host' => env('MAIL_DB_HOST', '127.0.0.1'),
            'database' => env('MAIL_DB_DATABASE', 'npanel_mail'),
            'username' => env('MAIL_DB_USERNAME', 'npanel_mail'),
            'password' => env('MAIL_DB_PASSWORD', ''),
        ];
    }

    /**
     * Generate all Dovecot configuration files.
     * Called during initial mail server setup.
     *
     * @return void
     * @throws Exception
     */
    public function generateAllConfigs(): void
    {
        $this->updateSqlAuthConfig(); // Now includes inline SQL config (Dovecot 2.4+ format)
        $this->updateAuthConfig();
        $this->updateMailConfig();
        $this->updateMasterConfig();

        Log::info("Generated all Dovecot configuration files");
    }
}
