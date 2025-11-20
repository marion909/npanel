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

        return View::make('templates.nginx.domain', [
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

        // Determine SSL paths (use parent domain's certificate or own)
        $sslCertPath = $subdomain->ssl_enabled
            ? ($parentDomain->ssl_cert_path ?? '')
            : '';
        $sslKeyPath = $subdomain->ssl_enabled
            ? ($parentDomain->ssl_key_path ?? '')
            : '';

        $phpFpmSocket = config('npanel.php_fpm_socket_dir') . '/php' 
            . ($subdomain->php_version ?? $parentDomain->php_version) 
            . '-fpm-' . Str::slug($subdomain->full_domain) . '.sock';

        return View::make('templates.nginx.subdomain', [
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

        File::put($configPath, $content);

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

        // Remove existing symlink if present
        if (File::exists($target)) {
            File::delete($target);
        }

        return symlink($source, $target);
    }

    /**
     * Disable site by removing symlink
     */
    public function disableSite(string $domainName): bool
    {
        $target = $this->sitesEnabled . '/' . $domainName . '.conf';

        if (File::exists($target)) {
            return File::delete($target);
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

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output),
            'return_code' => $returnCode,
        ];
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
        File::copy($configPath, $backupPath);

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
                File::delete($file);
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
            return File::delete($configPath);
        }

        return true;
    }
}
