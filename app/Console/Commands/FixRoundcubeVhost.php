<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class FixRoundcubeVhost extends Command
{
    protected $signature = 'npanel:fix-roundcube-vhost {--domain=}';
    protected $description = 'Fix Roundcube Nginx vhost configuration';

    public function handle()
    {
        $domain = $this->option('domain');
        
        if (!$domain) {
            $hostnameResult = Process::run('hostname -f');
            $hostname = trim($hostnameResult->output());
            $domain = "webmail.{$hostname}";
            $this->info("Auto-detected domain: {$domain}");
        }

        $this->info("Fixing Roundcube vhost for {$domain}...");

        // Create proper vhost configuration
        $config = <<<NGINX
server {
    listen 80;
    server_name {$domain};

    # ACME challenge directory
    location /.well-known/acme-challenge/ {
        root /var/www/roundcube;
        try_files \$uri =404;
    }

    # Redirect HTTP to HTTPS
    location / {
        return 301 https://\$server_name\$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name {$domain};

    root /var/www/roundcube;
    index index.php index.html;

    # SSL Configuration (self-signed initially)
    ssl_certificate /etc/ssl/certs/ssl-cert-snakeoil.pem;
    ssl_certificate_key /etc/ssl/private/ssl-cert-snakeoil.key;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|svn|git) {
        deny all;
    }
}
NGINX;

        // Write vhost file
        File::put('/etc/nginx/sites-available/roundcube.conf', $config);
        $this->info("✓ Vhost configuration written");

        // Remove old symlinks
        if (File::exists('/etc/nginx/sites-enabled/roundcube.conf')) {
            unlink('/etc/nginx/sites-enabled/roundcube.conf');
        }

        // Create new symlink with priority
        symlink('/etc/nginx/sites-available/roundcube.conf', '/etc/nginx/sites-enabled/00-roundcube.conf');
        $this->info("✓ Symlink created with priority (00-roundcube.conf)");

        // Ensure ACME directory exists
        $acmeDir = '/var/www/roundcube/.well-known/acme-challenge';
        if (!File::exists($acmeDir)) {
            File::makeDirectory($acmeDir, 0755, true);
        }
        Process::run('chown -R www-data:www-data /var/www/roundcube/.well-known');
        Process::run('chmod -R 755 /var/www/roundcube/.well-known');
        $this->info("✓ ACME challenge directory ready");

        // Test Nginx config
        $testResult = Process::run('nginx -t');
        if (!$testResult->successful()) {
            $this->error("Nginx config test failed:");
            $this->line($testResult->errorOutput());
            return 1;
        }
        $this->info("✓ Nginx config test passed");

        // Reload Nginx
        Process::run('systemctl reload nginx');
        $this->info("✓ Nginx reloaded");

        // Test if accessible
        $this->info("\nTesting accessibility...");
        $testFile = "{$acmeDir}/test-" . time() . ".txt";
        File::put($testFile, "test");
        
        sleep(1);
        
        $testUrl = "http://{$domain}/.well-known/acme-challenge/" . basename($testFile);
        $curlResult = Process::run("curl -s {$testUrl}");
        
        if (trim($curlResult->output()) === 'test') {
            $this->info("✓ ACME challenge is accessible!");
            unlink($testFile);
            
            $this->info("\n✅ Roundcube vhost fixed successfully!");
            $this->info("You can now issue SSL certificate with:");
            $this->info("  php artisan npanel:roundcube-ssl --domain={$domain}");
            return 0;
        } else {
            $this->error("✗ ACME challenge still not accessible");
            $this->line("Response: " . $curlResult->output());
            $this->line("\nDebug info:");
            $this->line("1. Check if file exists: ls -la {$testFile}");
            $this->line("2. Check Nginx error log: tail -20 /var/log/nginx/error.log");
            $this->line("3. Check which vhost is matching: nginx -T | grep -B10 '{$domain}'");
            return 1;
        }
    }
}
