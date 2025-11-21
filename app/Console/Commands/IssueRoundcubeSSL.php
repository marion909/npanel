<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class IssueRoundcubeSSL extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'npanel:roundcube-ssl {--domain=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Issue or renew SSL certificate for Roundcube webmail';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $webmailDomain = $this->option('domain');

        if (!$webmailDomain) {
            // Auto-detect from hostname
            $hostnameResult = Process::run('hostname -f');
            $hostname = trim($hostnameResult->output());
            $webmailDomain = "webmail.{$hostname}";
            
            $this->info("Auto-detected domain: {$webmailDomain}");
        }

        $this->info("Issuing SSL certificate for {$webmailDomain}...");

        // Get acme.sh path
        $acmePath = config('npanel.acme_sh_path', '/root/.acme.sh/acme.sh');

        if (!File::exists($acmePath)) {
            $this->error("acme.sh not found at {$acmePath}");
            $this->info("Please install acme.sh first: curl https://get.acme.sh | sh");
            return 1;
        }

        // Check if Roundcube is installed
        $webrootPath = '/var/www/roundcube';
        if (!File::exists($webrootPath)) {
            $this->error("Roundcube not found at {$webrootPath}");
            return 1;
        }

        // Create .well-known/acme-challenge directory
        $challengeDir = "{$webrootPath}/.well-known/acme-challenge";
        if (!File::exists($challengeDir)) {
            File::makeDirectory($challengeDir, 0755, true);
            $this->info("Created ACME challenge directory");
        }

        // Issue certificate
        $this->info("Requesting certificate from Let's Encrypt...");
        $issueCmd = "{$acmePath} --issue -d {$webmailDomain} -w {$webrootPath} --server letsencrypt --force";
        $result = Process::timeout(300)->run($issueCmd);

        if (!$result->successful()) {
            $this->error("Failed to issue certificate:");
            $this->line($result->errorOutput());
            return 1;
        }

        $this->info("Certificate issued successfully!");

        // Install certificate
        $certDir = "/etc/letsencrypt/live/{$webmailDomain}";
        if (!File::exists($certDir)) {
            File::makeDirectory($certDir, 0755, true);
        }

        $this->info("Installing certificate...");
        $installCmd = "{$acmePath} --install-cert -d {$webmailDomain} " .
            "--cert-file {$certDir}/cert.pem " .
            "--key-file {$certDir}/privkey.pem " .
            "--fullchain-file {$certDir}/fullchain.pem " .
            "--reloadcmd 'systemctl reload nginx'";

        $installResult = Process::timeout(120)->run($installCmd);

        if (!$installResult->successful()) {
            $this->error("Failed to install certificate:");
            $this->line($installResult->errorOutput());
            return 1;
        }

        // Update Nginx configuration
        $this->info("Updating Nginx configuration...");
        $this->updateNginxConfig($webmailDomain, $certDir);

        $this->info("✓ SSL certificate issued and installed successfully!");
        $this->info("Roundcube is now accessible at: https://{$webmailDomain}");

        return 0;
    }

    /**
     * Update Nginx configuration to use Let's Encrypt certificate.
     */
    private function updateNginxConfig(string $webmailDomain, string $certDir): void
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
        if (!$testResult->successful()) {
            $this->error("Nginx config test failed:");
            $this->line($testResult->errorOutput());
            return;
        }

        Process::run('systemctl reload nginx');
        $this->info("Nginx configuration updated and reloaded");
    }
}
