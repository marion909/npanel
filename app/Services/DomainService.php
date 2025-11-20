<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Subdomain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DomainService
{
    public function __construct(
        protected NginxService $nginxService,
        protected PhpFpmService $phpFpmService,
        protected SSLService $sslService
    ) {}

    /**
     * Create a new domain with basic setup (async activation via job)
     */
    public function createDomain(User $user, array $data): Domain
    {
        return DB::transaction(function () use ($user, $data) {
            // Create domain record
            $domain = Domain::create([
                'user_id' => $user->id,
                'domain_name' => $data['domain_name'],
                'document_root' => $data['document_root'] ?? $this->getDefaultDocumentRoot($data['domain_name']),
                'php_version' => $data['php_version'] ?? config('npanel.default_php_version'),
                'ssl_enabled' => $data['ssl_enabled'] ?? false,
                'status' => 'pending',
            ]);

            // Note: Directory creation, PHP-FPM pool, Nginx config, and subdomains
            // will be created in ActivateDomainJob (requires sudo, runs async)

            return $domain;
        });
    }

    /**
     * Get default document root for a domain
     */
    protected function getDefaultDocumentRoot(string $domainName): string
    {
        $basePath = config('npanel.base_path');
        $username = config('npanel.default_user');

        return "{$basePath}/{$username}/domains/{$domainName}/public_html";
    }

    /**
     * Create directory structure for domain
     */
    protected function createDirectoryStructure(Domain $domain): void
    {
        $baseDir = dirname($domain->document_root);

        // Create main directories
        $directories = [
            $domain->document_root,
            "{$baseDir}/logs",
            "{$baseDir}/tmp",
            "{$baseDir}/subdomains",
            "{$baseDir}/backups",
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }

        // Create default index.html
        $indexPath = $domain->document_root . '/index.html';
        if (!File::exists($indexPath)) {
            File::put($indexPath, $this->getDefaultIndexContent($domain->domain_name));
        }
    }

    /**
     * Get default index.html content
     */
    protected function getDefaultIndexContent(string $domainName): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {$domainName}</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #333; }
        p { color: #666; }
    </style>
</head>
<body>
    <h1>Welcome to {$domainName}</h1>
    <p>Your domain has been successfully configured!</p>
    <p>This is the default page. Replace this file to customize your website.</p>
</body>
</html>
HTML;
    }

    /**
     * Create default subdomains (www and @)
     */
    protected function createDefaultSubdomains(Domain $domain): void
    {
        $baseDir = dirname($domain->document_root);

        // Create 'www' subdomain
        Subdomain::create([
            'parent_domain_id' => $domain->id,
            'subdomain_name' => 'www',
            'document_root' => $domain->document_root, // Point to same root as main domain
            'php_version' => $domain->php_version,
            'ssl_enabled' => false,
        ]);

        // Create '@' subdomain (apex/root domain)
        Subdomain::create([
            'parent_domain_id' => $domain->id,
            'subdomain_name' => '@',
            'document_root' => $domain->document_root, // Point to same root as main domain
            'php_version' => $domain->php_version,
            'ssl_enabled' => false,
        ]);
    }

    /**
     * Update domain configuration
     */
    public function updateDomain(Domain $domain, array $data): Domain
    {
        return DB::transaction(function () use ($domain, $data) {
            $oldPhpVersion = $domain->php_version;

            // Update domain record
            $domain->update($data);

            // If PHP version changed, recreate pool
            if (isset($data['php_version']) && $data['php_version'] !== $oldPhpVersion) {
                $this->recreatePhpFpmPool($domain);
            }

            // Regenerate Nginx config
            $nginxConfig = $this->nginxService->generateDomainConfig($domain);
            $this->nginxService->writeConfig($domain->domain_name, $nginxConfig);

            return $domain->fresh();
        });
    }

    /**
     * Recreate PHP-FPM pool with new version
     */
    protected function recreatePhpFpmPool(Domain $domain): void
    {
        // Remove old pool
        if ($domain->phpFpmPool) {
            $this->phpFpmService->removePool($domain->phpFpmPool);
            $domain->phpFpmPool->delete();
        }

        // Create new pool
        $pool = $this->phpFpmService->createPool($domain);
        $domain->update(['php_fpm_pool' => $pool->pool_name]);
    }

    /**
     * Delete domain and cleanup resources
     */
    public function deleteDomain(Domain $domain): bool
    {
        return DB::transaction(function () use ($domain) {
            // Remove Nginx config
            $this->nginxService->removeConfig($domain->domain_name);

            // Remove PHP-FPM pool
            if ($domain->phpFpmPool) {
                $this->phpFpmService->removePool($domain->phpFpmPool);
            }

            // Mark as deleted (soft delete approach)
            $domain->update(['status' => 'deleted']);

            // Optionally: Remove directory structure (be careful!)
            // $this->removeDirectoryStructure($domain);

            return true;
        });
    }

    /**
     * Activate domain (deploy configs and reload services)
     */
    public function activateDomain(Domain $domain): void
    {
        // Create directory structure (with sudo)
        $this->createDirectoryStructure($domain);

        // Create default subdomains (www and @)
        $this->createDefaultSubdomains($domain);

        // Create PHP-FPM pool
        $pool = $this->phpFpmService->createPool($domain);
        $domain->update(['php_fpm_pool' => $pool->pool_name]);

        // Generate and deploy Nginx configuration
        $nginxConfig = $this->nginxService->generateDomainConfig($domain);
        $configPath = $this->nginxService->writeConfig($domain->domain_name, $nginxConfig);
        $domain->update(['nginx_config_path' => $configPath]);

        // Enable site (creates symlink)
        $this->nginxService->enableSite($domain->domain_name);

        // Test PHP-FPM config
        $phpTest = $this->phpFpmService->testConfig($domain->php_version);
        if (!$phpTest['success']) {
            throw new \Exception("PHP-FPM configuration test failed: " . $phpTest['output']);
        }

        // Test Nginx config
        $nginxTest = $this->nginxService->testConfig();
        if (!$nginxTest['success']) {
            throw new \Exception("Nginx configuration test failed: " . $nginxTest['output']);
        }

        // Reload services
        $this->phpFpmService->reload($domain->php_version);
        $this->nginxService->reload();

        // Update domain status
        $domain->update(['status' => 'active']);

        // Trigger SSL certificate issuance if enabled
        if ($domain->ssl_enabled) {
            $this->sslService->issueCertificate($domain);
        }
    }

    /**
     * Suspend domain
     */
    public function suspendDomain(Domain $domain): void
    {
        $this->nginxService->disableSite($domain->domain_name);
        $this->nginxService->reload();
        $domain->update(['status' => 'suspended']);
    }

    /**
     * Resume suspended domain
     */
    public function resumeDomain(Domain $domain): void
    {
        $this->nginxService->enableSite($domain->domain_name);
        $this->nginxService->reload();
        $domain->update(['status' => 'active']);
    }
}
