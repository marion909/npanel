<?php

namespace App\Jobs;

use App\Services\PostfixService;
use App\Services\DovecotService;
use App\Services\SSLService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Exception;

class InstallMailServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries = 1;

    private PostfixService $postfixService;
    private DovecotService $dovecotService;
    private SSLService $sslService;

    /**
     * Execute the job.
     */
    public function handle(PostfixService $postfixService, DovecotService $dovecotService, SSLService $sslService): void
    {
        $this->postfixService = $postfixService;
        $this->dovecotService = $dovecotService;
        $this->sslService = $sslService;

        Log::info("Starting mail server installation...");

        try {
            $this->installPackages();
            $this->createVmailUser();
            $this->configurePostfix();
            $this->configureDovecot();
            $this->configureOpenDKIM();
            $this->installRoundcube();
            $this->issueRoundcubeSSL();
            $this->startServices();

            Log::info("Mail server installation completed successfully");
        } catch (Exception $e) {
            Log::error("Mail server installation failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Install required packages.
     */
    private function installPackages(): void
    {
        Log::info("Installing mail server packages...");

        $packages = [
            'postfix',
            'postfix-mysql',
            'dovecot-core',
            'dovecot-imapd',
            'dovecot-lmtpd',
            'dovecot-mysql',
            'opendkim',
            'opendkim-tools',
        ];

        // Update package list
        Process::run('apt-get update');

        // Install packages non-interactively
        $packageList = implode(' ', $packages);
        $result = Process::run("DEBIAN_FRONTEND=noninteractive apt-get install -y {$packageList}");

        if (!$result->successful()) {
            throw new Exception("Failed to install mail server packages: " . $result->errorOutput());
        }

        Log::info("Mail server packages installed");
    }

    /**
     * Create vmail user and directory structure.
     */
    private function createVmailUser(): void
    {
        Log::info("Creating vmail user...");

        // Check if vmail user already exists
        $result = Process::run('id vmail');
        if ($result->successful()) {
            Log::info("vmail user already exists");
            return;
        }

        // Create vmail user with UID 5000
        Process::run('groupadd -g 5000 vmail');
        Process::run('useradd -u 5000 -g vmail -s /usr/sbin/nologin -d /var/vmail -m vmail');

        // Create vmail directory
        if (!File::exists('/var/vmail')) {
            File::makeDirectory('/var/vmail', 0750, true);
            Process::run('chown -R vmail:vmail /var/vmail');
        }

        Log::info("vmail user created with UID 5000");
    }

    /**
     * Configure Postfix for virtual mailboxes.
     */
    private function configurePostfix(): void
    {
        Log::info("Configuring Postfix...");

        // Generate MySQL config files
        $this->postfixService->generateConfigs();

        // Update main.cf with virtual mailbox settings
        $this->postfixService->updateMainConfig();

        // Set postfix as internet site
        $hostnameResult = Process::run('hostname -f');
        $hostname = trim($hostnameResult->output());

        // Configure myhostname
        Process::run("postconf -e 'myhostname={$hostname}'");
        Process::run("postconf -e 'mydestination=localhost'");

        Log::info("Postfix configured");
    }

    /**
     * Configure Dovecot for IMAP and LMTP.
     */
    private function configureDovecot(): void
    {
        Log::info("Configuring Dovecot...");

        $this->dovecotService->generateAllConfigs();

        Log::info("Dovecot configured");
    }

    /**
     * Configure OpenDKIM for email signing.
     */
    private function configureOpenDKIM(): void
    {
        Log::info("Configuring OpenDKIM...");

        // Create OpenDKIM directories
        if (!File::exists('/etc/opendkim')) {
            File::makeDirectory('/etc/opendkim', 0755, true);
        }
        if (!File::exists('/etc/opendkim/keys')) {
            File::makeDirectory('/etc/opendkim/keys', 0750, true);
        }

        // Configure opendkim.conf
        $opendkimConf = <<<EOF
# nPanel OpenDKIM Configuration
Syslog yes
SyslogSuccess yes
LogWhy yes

# Mode
Mode sv
SubDomains yes

# Canonicalization
Canonicalization relaxed/simple

# Socket
Socket unix:/var/run/opendkim/opendkim.sock

# User
UserID opendkim:opendkim

# Hosts to ignore when verifying signatures
ExternalIgnoreList refile:/etc/opendkim/TrustedHosts
InternalHosts refile:/etc/opendkim/TrustedHosts

# Keys
KeyTable refile:/etc/opendkim/KeyTable
SigningTable refile:/etc/opendkim/SigningTable

# Signing
AutoRestart yes
AutoRestartRate 10/1h
Background yes
DNSTimeout 5
SignatureAlgorithm rsa-sha256

EOF;

        File::put('/etc/opendkim.conf', $opendkimConf);

        // Create TrustedHosts
        $trustedHosts = <<<EOF
127.0.0.1
localhost
192.168.0.0/16
172.16.0.0/12
10.0.0.0/8

EOF;
        File::put('/etc/opendkim/TrustedHosts', $trustedHosts);

        // Create empty KeyTable and SigningTable (will be populated per domain)
        File::put('/etc/opendkim/KeyTable', "# domain selector keyfile\n");
        File::put('/etc/opendkim/SigningTable', "# pattern domain\n");

        // Set permissions
        Process::run('chown -R opendkim:opendkim /etc/opendkim');
        Process::run('chmod 600 /etc/opendkim/*.conf 2>/dev/null || true');

        Log::info("OpenDKIM configured");
    }

    /**
     * Install Roundcube webmail.
     */
    private function installRoundcube(): void
    {
        Log::info("Installing Roundcube...");

        $roundcubeVersion = '1.6.5';
        $roundcubePath = '/var/www/roundcube';

        // Check if Roundcube already exists
        if (File::exists($roundcubePath)) {
            Log::info("Roundcube already installed at {$roundcubePath}");
            return;
        }

        // Download Roundcube
        $downloadUrl = "https://github.com/roundcube/roundcubemail/releases/download/{$roundcubeVersion}/roundcubemail-{$roundcubeVersion}-complete.tar.gz";
        $tempFile = "/tmp/roundcube-{$roundcubeVersion}.tar.gz";

        $result = Process::run("wget -O {$tempFile} {$downloadUrl}");
        if (!$result->successful()) {
            throw new Exception("Failed to download Roundcube: " . $result->errorOutput());
        }

        // Extract
        Process::run("tar -xzf {$tempFile} -C /var/www/");
        Process::run("mv /var/www/roundcubemail-{$roundcubeVersion} {$roundcubePath}");
        Process::run("rm {$tempFile}");

        // Set permissions
        Process::run("chown -R www-data:www-data {$roundcubePath}");
        Process::run("chmod 755 {$roundcubePath}");

        // Create Nginx vhost for webmail.{server_hostname}
        $this->createRoundcubeVhost();

        // Configure Roundcube config.inc.php
        $this->configureRoundcube($roundcubePath);

        Log::info("Roundcube installed at {$roundcubePath}");
    }

    /**
     * Create Nginx virtual host for Roundcube.
     */
    private function createRoundcubeVhost(): void
    {
        $hostnameResult = Process::run('hostname -f');
        $hostname = trim($hostnameResult->output());
        $webmailDomain = "webmail.{$hostname}";

        $nginxConf = <<<EOF
server {
    listen 80;
    server_name {$webmailDomain};

    # ACME challenge directory
    location /.well-known/acme-challenge/ {
        root /var/www/roundcube;
    }

    # Redirect HTTP to HTTPS
    location / {
        return 301 https://\$server_name\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name {$webmailDomain};

    root /var/www/roundcube;
    index index.php index.html;

    # SSL Configuration (self-signed initially, will be replaced with Let's Encrypt)
    ssl_certificate /etc/ssl/certs/ssl-cert-snakeoil.pem;
    ssl_certificate_key /etc/ssl/private/ssl-cert-snakeoil.key;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|svn|git) {
        deny all;
    }
}

EOF;

        File::put("/etc/nginx/sites-available/roundcube.conf", $nginxConf);
        
        // Create symlink
        if (!File::exists('/etc/nginx/sites-enabled/roundcube.conf')) {
            Process::run('ln -s /etc/nginx/sites-available/roundcube.conf /etc/nginx/sites-enabled/');
        }

        // Test and reload Nginx
        $testResult = Process::run('nginx -t');
        if ($testResult->successful()) {
            Process::run('systemctl reload nginx');
            Log::info("Roundcube Nginx vhost created for {$webmailDomain}");
        } else {
            Log::error("Nginx config test failed for Roundcube: " . $testResult->errorOutput());
        }
    }

    /**
     * Configure Roundcube config.inc.php.
     */
    private function configureRoundcube(string $roundcubePath): void
    {
        $configPath = "{$roundcubePath}/config/config.inc.php";

        // Get database credentials from Laravel
        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbName = config('database.connections.mysql.database', 'npanel');
        $dbUser = config('database.connections.mysql.username', 'npanel');
        $dbPass = config('database.connections.mysql.password', '');

        $config = <<<EOF
<?php

\$config = [];

// Database
\$config['db_dsnw'] = 'mysql://{$dbUser}:{$dbPass}@{$dbHost}/{$dbName}';

// IMAP
\$config['imap_host'] = 'ssl://localhost:993';
\$config['imap_auth_type'] = 'LOGIN';
\$config['imap_delimiter'] = '/';

// SMTP
\$config['smtp_host'] = 'tls://localhost:587';
\$config['smtp_auth_type'] = 'LOGIN';
\$config['smtp_user'] = '%u';
\$config['smtp_pass'] = '%p';

// Display
\$config['product_name'] = 'nPanel Webmail';
\$config['des_key'] = '<?php echo bin2hex(random_bytes(24)); ?>';
\$config['cipher_method'] = 'AES-256-CBC';

// Plugins
\$config['plugins'] = ['archive', 'zipdownload'];

// Misc
\$config['enable_installer'] = false;
\$config['log_driver'] = 'syslog';
\$config['syslog_facility'] = LOG_MAIL;

EOF;

        File::put($configPath, $config);
        Process::run("chown www-data:www-data {$configPath}");
        Process::run("chmod 640 {$configPath}");

        Log::info("Roundcube configured");
    }

    /**
     * Issue SSL certificate for Roundcube domain.
     */
    private function issueRoundcubeSSL(): void
    {
        Log::info("Issuing SSL certificate for Roundcube...");

        $hostnameResult = Process::run('hostname -f');
        $hostname = trim($hostnameResult->output());
        $webmailDomain = "webmail.{$hostname}";

        try {
            // Get acme.sh path from config
            $acmePath = config('npanel.acme_sh_path', '/root/.acme.sh/acme.sh');

            if (!File::exists($acmePath)) {
                Log::warning("acme.sh not found at {$acmePath}. Skipping SSL certificate issuance.");
                Log::info("You can manually issue certificate later with: {$acmePath} --issue -d {$webmailDomain} -w /var/www/roundcube");
                return;
            }

            // Create .well-known/acme-challenge directory
            $webrootPath = '/var/www/roundcube';
            $challengeDir = "{$webrootPath}/.well-known/acme-challenge";
            if (!File::exists($challengeDir)) {
                File::makeDirectory($challengeDir, 0755, true);
            }

            // Issue certificate using HTTP-01 challenge
            $issueCmd = "{$acmePath} --issue -d {$webmailDomain} -w {$webrootPath} --server letsencrypt --force";
            $result = Process::timeout(300)->run($issueCmd);

            if (!$result->successful()) {
                Log::warning("Failed to issue SSL certificate for {$webmailDomain}: " . $result->errorOutput());
                Log::info("Roundcube will continue using self-signed certificate. You can issue certificate manually later.");
                return;
            }

            // Install certificate
            $certDir = "/etc/letsencrypt/live/{$webmailDomain}";
            if (!File::exists($certDir)) {
                File::makeDirectory($certDir, 0755, true);
            }

            $installCmd = "{$acmePath} --install-cert -d {$webmailDomain} " .
                "--cert-file {$certDir}/cert.pem " .
                "--key-file {$certDir}/privkey.pem " .
                "--fullchain-file {$certDir}/fullchain.pem " .
                "--reloadcmd 'systemctl reload nginx'";

            $installResult = Process::timeout(120)->run($installCmd);

            if (!$installResult->successful()) {
                Log::warning("Failed to install SSL certificate: " . $installResult->errorOutput());
                return;
            }

            // Update Nginx configuration to use Let's Encrypt certificate
            $this->updateRoundcubeVhostSSL($webmailDomain, $certDir);

            Log::info("SSL certificate issued and installed for {$webmailDomain}");

        } catch (Exception $e) {
            Log::error("Error issuing SSL certificate for Roundcube: " . $e->getMessage());
            Log::info("Roundcube will continue using self-signed certificate.");
        }
    }

    /**
     * Update Roundcube Nginx vhost to use Let's Encrypt certificate.
     */
    private function updateRoundcubeVhostSSL(string $webmailDomain, string $certDir): void
    {
        $nginxConf = <<<EOF
server {
    listen 80;
    server_name {$webmailDomain};

    # ACME challenge
    location /.well-known/acme-challenge/ {
        root /var/www/roundcube;
    }

    # Redirect HTTP to HTTPS
    location / {
        return 301 https://\$server_name\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name {$webmailDomain};

    root /var/www/roundcube;
    index index.php index.html;

    # SSL Configuration - Let's Encrypt
    ssl_certificate {$certDir}/fullchain.pem;
    ssl_certificate_key {$certDir}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|svn|git) {
        deny all;
    }
}

EOF;

        File::put("/etc/nginx/sites-available/roundcube.conf", $nginxConf);

        // Test and reload Nginx
        $testResult = Process::run('nginx -t');
        if ($testResult->successful()) {
            Process::run('systemctl reload nginx');
            Log::info("Roundcube Nginx vhost updated with SSL certificate");
        } else {
            Log::error("Nginx config test failed after SSL update: " . $testResult->errorOutput());
        }
    }

    /**
     * Start and enable all mail services.
     */
    private function startServices(): void
    {
        Log::info("Starting mail services...");

        $services = ['postfix', 'dovecot', 'opendkim'];

        foreach ($services as $service) {
            Process::run("systemctl enable {$service}");
            Process::run("systemctl restart {$service}");
            
            $statusResult = Process::run("systemctl is-active {$service}");
            if (trim($statusResult->output()) === 'active') {
                Log::info("{$service} started successfully");
            } else {
                Log::warning("{$service} may not be running properly");
            }
        }

        Log::info("Mail services started");
    }
}
