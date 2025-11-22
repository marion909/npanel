<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class PostfixService
{
    private string $configPath = '/etc/postfix';
    private string $mysqlConfigPath = '/etc/postfix/mysql';

    /**
     * Generate all Postfix MySQL configuration files.
     *
     * @return void
     * @throws Exception
     */
    public function generateConfigs(): void
    {
        // Ensure MySQL config directory exists
        if (!File::exists($this->mysqlConfigPath)) {
            File::makeDirectory($this->mysqlConfigPath, 0755, true);
        }

        $this->generateDomainsConfig();
        $this->generateMailboxesConfig();
        $this->generateAliasesConfig();

        Log::info("Generated Postfix MySQL configuration files");
    }

    /**
     * Test Postfix configuration.
     *
     * @return bool
     */
    public function testConfig(): bool
    {
        $result = Process::run('postfix check');
        
        if (!$result->successful()) {
            Log::error("Postfix config test failed: " . $result->errorOutput());
            return false;
        }

        return true;
    }

    /**
     * Reload Postfix service.
     *
     * @return void
     * @throws Exception
     */
    public function reload(): void
    {
        if (!$this->testConfig()) {
            throw new Exception("Postfix configuration test failed. Not reloading.");
        }

        $result = Process::run('systemctl reload postfix');

        if (!$result->successful()) {
            Log::error("Failed to reload Postfix: " . $result->errorOutput());
            throw new Exception("Failed to reload Postfix service.");
        }

        Log::info("Postfix reloaded successfully");
    }

    /**
     * Generate MySQL virtual domains configuration.
     *
     * @return void
     */
    private function generateDomainsConfig(): void
    {
        $dbConfig = $this->getDatabaseConfig();

        $content = <<<EOF
# MySQL virtual domains configuration
user = {$dbConfig['username']}
password = {$dbConfig['password']}
hosts = {$dbConfig['host']}
dbname = {$dbConfig['database']}
query = SELECT domain_name FROM domains WHERE domain_name='%s' AND status='active'

EOF;

        File::put("{$this->mysqlConfigPath}/virtual-domains.cf", $content);
        Log::info("Generated virtual-domains.cf");
    }

    /**
     * Generate MySQL virtual mailboxes configuration.
     *
     * @return void
     */
    private function generateMailboxesConfig(): void
    {
        $dbConfig = $this->getDatabaseConfig();

        $content = <<<EOF
# MySQL virtual mailboxes configuration
user = {$dbConfig['username']}
password = {$dbConfig['password']}
hosts = {$dbConfig['host']}
dbname = {$dbConfig['database']}
query = SELECT CONCAT(SUBSTRING_INDEX(email, '@', 1), '/', 'Maildir/') AS maildir FROM mailboxes WHERE email='%s' AND status='active'

EOF;

        File::put("{$this->mysqlConfigPath}/virtual-mailboxes.cf", $content);
        Log::info("Generated virtual-mailboxes.cf");
    }

    /**
     * Generate MySQL virtual aliases configuration with catch-all support.
     * Priority: exact mailbox → alias → catch-all
     *
     * @return void
     */
    private function generateAliasesConfig(): void
    {
        $dbConfig = $this->getDatabaseConfig();

        $content = <<<EOF
# MySQL virtual aliases configuration with catch-all support
user = {$dbConfig['username']}
password = {$dbConfig['password']}
hosts = {$dbConfig['host']}
dbname = {$dbConfig['database']}

# Query handles priority: mailbox (if exists) → alias → catch-all
query = 
    SELECT email AS destination FROM mailboxes WHERE email='%s' AND status='active'
    UNION
    SELECT destination FROM mail_aliases WHERE source='%s'
    UNION
    SELECT destination FROM mail_aliases WHERE source=CONCAT('@', SUBSTRING_INDEX('%s', '@', -1)) AND type='catchall'
    LIMIT 1

EOF;

        File::put("{$this->mysqlConfigPath}/virtual-aliases.cf", $content);
        Log::info("Generated virtual-aliases.cf");
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
     * Update main.cf with virtual mailbox settings.
     * This should be called during initial mail server setup.
     *
     * @return void
     * @throws Exception
     */
    public function updateMainConfig(): void
    {
        $mainCfPath = "{$this->configPath}/main.cf";

        if (!File::exists($mainCfPath)) {
            throw new Exception("Postfix main.cf not found at {$mainCfPath}");
        }

        // Backup current config
        File::copy($mainCfPath, "{$mainCfPath}.backup." . time());

        $virtualConfig = <<<EOF

# nPanel Virtual Mailbox Configuration
virtual_mailbox_domains = mysql:{$this->mysqlConfigPath}/virtual-domains.cf
virtual_mailbox_maps = mysql:{$this->mysqlConfigPath}/virtual-mailboxes.cf
virtual_alias_maps = mysql:{$this->mysqlConfigPath}/virtual-aliases.cf
virtual_mailbox_base = /var/vmail
virtual_uid_maps = static:5000
virtual_gid_maps = static:5000
virtual_transport = lmtp:unix:private/dovecot-lmtp

# SMTP TLS
smtpd_tls_cert_file = /etc/ssl/certs/ssl-cert-snakeoil.pem
smtpd_tls_key_file = /etc/ssl/private/ssl-cert-snakeoil.key
smtpd_tls_security_level = may
smtp_tls_security_level = may

# SASL Authentication via Dovecot
smtpd_sasl_type = dovecot
smtpd_sasl_path = private/auth
smtpd_sasl_auth_enable = yes

# SMTP Restrictions
smtpd_recipient_restrictions =
    permit_sasl_authenticated,
    permit_mynetworks,
    reject_unauth_destination

# OpenDKIM Integration
milter_default_action = accept
milter_protocol = 6
smtpd_milters = unix:/var/run/opendkim/opendkim.sock
non_smtpd_milters = \$smtpd_milters

EOF;

        // Append virtual config if not already present
        $currentContent = File::get($mainCfPath);
        
        if (!str_contains($currentContent, 'nPanel Virtual Mailbox Configuration')) {
            File::append($mainCfPath, $virtualConfig);
            Log::info("Updated Postfix main.cf with virtual mailbox configuration");
        } else {
            Log::info("Postfix main.cf already contains virtual mailbox configuration");
        }
    }
}
