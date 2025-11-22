<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Subdomain;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class NginxService
{
    protected string $sitesAvailable;
    protected string $sitesEnabled;

    public function __construct()
    {
        $this->sitesAvailable = config('npanel.nginx_sites_available');
        $this->sitesEnabled = config('npanel.nginx_sites_enabled');
    }

    /**
     * Generate Nginx configuration for a domain
     */
    public function generateDomainConfig(Domain $domain): string
    {
        $phpFpmSocket = $domain->phpFpmPool
            ? $domain->phpFpmPool->socket_path
            : config('npanel.php_fpm_socket_dir') . '/php' . $domain->php_version . '-fpm-' . Str::slug($domain->domain_name) . '.sock';

        return View::make('templates/nginx/domain', [
            'domain' => $domain,
            'phpFpmSocket' => $phpFpmSocket,
        ])->render();
    }

    /**
     * Generate Nginx configuration for a subdomain
     */
    public function generateSubdomainConfig(Subdomain $subdomain): string
    {
        $parentDomain = $subdomain->parentDomain;

        // Determine SSL paths (use subdomain's own certificate if available, otherwise parent's)
        $sslCertPath = $subdomain->ssl_enabled
            ? ($subdomain->ssl_cert_path ?? $parentDomain->ssl_cert_path ?? '')
            : '';
        $sslKeyPath = $subdomain->ssl_enabled
            ? ($subdomain->ssl_key_path ?? $parentDomain->ssl_key_path ?? '')
            : '';

        // Determine PHP-FPM socket
        // If subdomain uses same PHP version as parent, use parent's pool
        // Otherwise, subdomain should have its own pool
        if ($subdomain->php_version === $parentDomain->php_version) {
            // Use parent domain's pool
            $phpFpmSocket = config('npanel.php_fpm_socket_dir') . '/php' 
                . $parentDomain->php_version 
                . '-fpm-' . Str::slug($parentDomain->domain_name) . '.sock';
        } else {
            // Use subdomain's own pool
            $phpFpmSocket = config('npanel.php_fpm_socket_dir') . '/php' 
                . $subdomain->php_version 
                . '-fpm-' . Str::slug($subdomain->full_domain) . '.sock';
        }

        return View::make('templates/nginx/subdomain', [
            'subdomain' => $subdomain,
            'sslCertPath' => $sslCertPath,
            'sslKeyPath' => $sslKeyPath,
            'phpFpmSocket' => $phpFpmSocket,
        ])->render();
    }

    /**
     * Write configuration file to sites-available
     */
    public function writeConfig(string $domainName, string $content): string
    {
        $configPath = $this->sitesAvailable . '/' . $domainName . '.conf';

        // Backup existing config if it exists
        if (File::exists($configPath)) {
            $this->backupConfig($configPath);
        }

        // Write to temp file first
        $tempPath = sys_get_temp_dir() . '/' . $domainName . '.conf';
        File::put($tempPath, $content);

        // Move with sudo
        $escapedTemp = escapeshellarg($tempPath);
        $escapedTarget = escapeshellarg($configPath);
        exec("sudo mv {$escapedTemp} {$escapedTarget}", $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \Exception("Failed to write nginx config: " . implode("\n", $output));
        }

        return $configPath;
    }

    /**
     * Create symlink in sites-enabled
     */
    public function enableSite(string $domainName): bool
    {
        $source = $this->sitesAvailable . '/' . $domainName . '.conf';
        $target = $this->sitesEnabled . '/' . $domainName . '.conf';

        if (!File::exists($source)) {
            throw new \Exception("Configuration file does not exist: {$source}");
        }

        // Remove existing symlink if present and create new one with sudo
        $escapedSource = escapeshellarg($source);
        $escapedTarget = escapeshellarg($target);
        
        if (File::exists($target)) {
            exec("sudo rm -f {$escapedTarget}");
        }

        exec("sudo ln -sf {$escapedSource} {$escapedTarget}", $output, $returnVar);
        
        return $returnVar === 0;
    }

    /**
     * Disable site by removing symlink
     */
    public function disableSite(string $domainName): bool
    {
        $target = $this->sitesEnabled . '/' . $domainName . '.conf';

        if (File::exists($target)) {
            $escapedTarget = escapeshellarg($target);
            exec("sudo rm -f {$escapedTarget}", $output, $returnVar);
            return $returnVar === 0;
        }

        return true;
    }

    /**
     * Test Nginx configuration
     */
    public function testConfig(): array
    {
        $command = config('npanel.nginx_config_test_command');
        $output = [];
        $returnCode = 0;

        exec($command . ' 2>&1', $output, $returnCode);

        $outputString = implode("\n", $output);
        
        // Fix common http2 directive issues automatically
        if (preg_match('/unknown directive "http2"/i', $outputString)) {
            $this->fixHttp2Directives();
            // Re-test after fixing
            exec($command . ' 2>&1', $output, $returnCode);
            $outputString = implode("\n", $output);
        }
        
        // Check if there's a real error (not just warnings)
        // nginx -t returns 0 on success, even with warnings
        // Only fail if there's an actual error line containing "emerg" or "error:"
        $hasError = (preg_match('/\[emerg\]|\[error\]|test failed/i', $outputString) && $returnCode !== 0);

        return [
            'success' => !$hasError,
            'output' => $outputString,
            'return_code' => $returnCode,
        ];
    }

    /**
     * Fix http2 directive issues in all Nginx configs
     */
    protected function fixHttp2Directives(): void
    {
        $configs = glob($this->sitesAvailable . '/*.conf');
        
        foreach ($configs as $configPath) {
            $content = File::get($configPath);
            $modified = false;
            
            // Fix: listen 443 ssl http2; -> listen 443 ssl;
            if (preg_match('/listen\s+443\s+ssl\s+http2;/', $content)) {
                $content = preg_replace('/listen\s+443\s+ssl\s+http2;/', 'listen 443 ssl;', $content);
                $modified = true;
            }
            
            // Fix: listen [::]:443 ssl http2; -> listen [::]:443 ssl;
            if (preg_match('/listen\s+\[::\]:443\s+ssl\s+http2;/', $content)) {
                $content = preg_replace('/listen\s+\[::\]:443\s+ssl\s+http2;/', 'listen [::]:443 ssl;', $content);
                $modified = true;
            }
            
            // Fix: http2 on; directive (remove it)
            if (preg_match('/\s+http2\s+on;/', $content)) {
                $content = preg_replace('/\s+http2\s+on;/', '', $content);
                $modified = true;
            }
            
            if ($modified) {
                File::put($configPath, $content);
                \Log::info("Fixed http2 directives in: {$configPath}");
            }
        }
    }

    /**
     * Reload Nginx service
     */
    public function reload(): array
    {
        // First test the configuration
        $testResult = $this->testConfig();

        if (!$testResult['success']) {
            throw new \Exception("Nginx configuration test failed: " . $testResult['output']);
        }

        $command = config('npanel.nginx_reload_command');
        $output = [];
        $returnCode = 0;

        exec($command . ' 2>&1', $output, $returnCode);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output),
            'return_code' => $returnCode,
        ];
    }

    /**
     * Backup configuration file
     */
    protected function backupConfig(string $configPath): void
    {
        $backupPath = $configPath . '.backup.' . now()->format('YmdHis');
        $escapedSource = escapeshellarg($configPath);
        $escapedBackup = escapeshellarg($backupPath);
        exec("sudo cp {$escapedSource} {$escapedBackup}");

        // Cleanup old backups
        $this->cleanupOldBackups($configPath);
    }

    /**
     * Cleanup old backup files
     */
    protected function cleanupOldBackups(string $configPath): void
    {
        $retention = config('npanel.config_backup_retention', 10);
        $backups = glob($configPath . '.backup.*');

        if (count($backups) > $retention) {
            // Sort by modification time (oldest first)
            usort($backups, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Remove oldest backups
            $toRemove = array_slice($backups, 0, count($backups) - $retention);
            foreach ($toRemove as $file) {
                $escapedFile = escapeshellarg($file);
                exec("sudo rm -f {$escapedFile}");
            }
        }
    }

    /**
     * Remove configuration and disable site
     */
    public function removeConfig(string $domainName): bool
    {
        // Disable site first
        $this->disableSite($domainName);

        // Remove config file
        $configPath = $this->sitesAvailable . '/' . $domainName . '.conf';
        if (File::exists($configPath)) {
            $escapedPath = escapeshellarg($configPath);
            exec("sudo rm -f {$escapedPath}", $output, $returnVar);
            return $returnVar === 0;
        }

        return true;
    }

    /**
     * Generate suspended configuration for a domain
     */
    public function generateSuspendedConfig(Domain $domain): void
    {
        // Ensure suspended page exists in a central location
        $this->ensureSuspendedPageExists();
        
        // Generate suspended Nginx config
        $config = $this->generateSuspendedNginxConfig($domain->domain_name, $domain->ssl_enabled, $domain->ssl_cert_path, $domain->ssl_key_path);
        $this->writeConfig($domain->domain_name, $config);
        $this->enableSite($domain->domain_name);
    }

    /**
     * Generate suspended configuration for a subdomain
     */
    public function generateSuspendedConfigForSubdomain(Subdomain $subdomain): void
    {
        // Ensure suspended page exists in a central location
        $this->ensureSuspendedPageExists();
        
        $parentDomain = $subdomain->parentDomain;
        
        // Determine full subdomain name
        $subdomainFullName = $subdomain->subdomain_name === '@' 
            ? $parentDomain->domain_name 
            : $subdomain->subdomain_name . '.' . $parentDomain->domain_name;
        
        // Determine SSL settings (use subdomain's cert if available, otherwise parent's)
        $sslEnabled = $subdomain->ssl_enabled;
        $sslCertPath = $subdomain->ssl_cert_path ?? $parentDomain->ssl_cert_path ?? '';
        $sslKeyPath = $subdomain->ssl_key_path ?? $parentDomain->ssl_key_path ?? '';
        
        // Generate suspended Nginx config
        $config = $this->generateSuspendedNginxConfig($subdomainFullName, $sslEnabled, $sslCertPath, $sslKeyPath);
        $this->writeConfig($subdomainFullName, $config);
        $this->enableSite($subdomainFullName);
    }

    /**
     * Ensure the suspended page HTML exists in /var/www/html
     */
    protected function ensureSuspendedPageExists(): void
    {
        $suspendedPagePath = '/var/www/html/suspended.html';
        
        if (!File::exists($suspendedPagePath)) {
            $suspendedHtml = View::make('templates/nginx/suspended')->render();
            
            // Write to temp file first
            $tempPath = sys_get_temp_dir() . '/suspended.html';
            File::put($tempPath, $suspendedHtml);
            
            // Move with sudo
            $escapedTemp = escapeshellarg($tempPath);
            $escapedTarget = escapeshellarg($suspendedPagePath);
            exec("sudo mv {$escapedTemp} {$escapedTarget}");
            exec("sudo chmod 644 {$escapedTarget}");
        }
    }

    /**
     * Generate suspended Nginx configuration content
     */
    protected function generateSuspendedNginxConfig(string $domainName, bool $sslEnabled, ?string $sslCertPath, ?string $sslKeyPath): string
    {
        $config = "# Suspended configuration for {$domainName}\n\n";
        
        // HTTP configuration - redirect to HTTPS if SSL enabled
        $config .= "server {\n";
        $config .= "    listen 80;\n";
        $config .= "    listen [::]:80;\n";
        $config .= "    server_name {$domainName};\n\n";
        
        if ($sslEnabled && $sslCertPath && $sslKeyPath) {
            $config .= "    return 301 https://\$server_name\$request_uri;\n";
        } else {
            $config .= "    root /var/www/html;\n";
            $config .= "    location / {\n";
            $config .= "        return 503;\n";
            $config .= "    }\n";
            $config .= "    error_page 503 /suspended.html;\n";
            $config .= "    location = /suspended.html {\n";
            $config .= "        internal;\n";
            $config .= "    }\n";
        }
        
        $config .= "}\n\n";
        
        // HTTPS configuration if SSL enabled
        if ($sslEnabled && $sslCertPath && $sslKeyPath) {
            $config .= "server {\n";
            $config .= "    listen 443 ssl http2;\n";
            $config .= "    listen [::]:443 ssl http2;\n";
            $config .= "    server_name {$domainName};\n\n";
            $config .= "    ssl_certificate {$sslCertPath};\n";
            $config .= "    ssl_certificate_key {$sslKeyPath};\n";
            $config .= "    ssl_protocols TLSv1.2 TLSv1.3;\n";
            $config .= "    ssl_ciphers HIGH:!aNULL:!MD5;\n\n";
            $config .= "    root /var/www/html;\n";
            $config .= "    location / {\n";
            $config .= "        return 503;\n";
            $config .= "    }\n";
            $config .= "    error_page 503 /suspended.html;\n";
            $config .= "    location = /suspended.html {\n";
            $config .= "        internal;\n";
            $config .= "    }\n";
            $config .= "}\n";
        }
        
        return $config;
    }
}

