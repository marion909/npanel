<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Subdomain;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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

        // Set ownership to www-data
        exec("sudo chown -R www-data:www-data " . escapeshellarg($baseDir));

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

        // Create 'www' subdomain if it doesn't exist
        Subdomain::firstOrCreate(
            [
                'parent_domain_id' => $domain->id,
                'subdomain_name' => 'www',
            ],
            [
                'document_root' => $domain->document_root, // Point to same root as main domain
                'php_version' => $domain->php_version,
                'ssl_enabled' => false,
            ]
        );

        // Create '@' subdomain (apex/root domain) if it doesn't exist
        Subdomain::firstOrCreate(
            [
                'parent_domain_id' => $domain->id,
                'subdomain_name' => '@',
            ],
            [
                'document_root' => $domain->document_root, // Point to same root as main domain
                'php_version' => $domain->php_version,
                'ssl_enabled' => false,
            ]
        );
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
     * Delete domain and all associated resources
     * SSL certificates are kept for potential reuse
     */
    public function deleteDomain(Domain $domain): bool
    {
        try {
            Log::info("Preparing domain deletion: {$domain->domain_name}");

            // Collect data before deletion for async job
            $domainName = $domain->domain_name;
            $documentRoot = $domain->document_root;
            $phpVersion = $domain->php_version;
            $phpFpmPool = $domain->php_fpm_pool;
            
            // Collect database info (name and user) before deleting records
            $databases = $domain->databases->map(function ($db) {
                return [
                    'name' => $db->database_name,
                    'user' => $db->mysql_user,
                ];
            })->toArray();
            
            $subdomainIds = $domain->subdomains->pluck('id')->toArray();

            // Check if domain has mailboxes (for cleanup flag)
            $hasMailboxes = $domain->mailboxes()->exists();

            DB::transaction(function () use ($domain) {
                // Delete subdomains records (configs will be deleted by job)
                $domain->subdomains()->delete();
                Log::info("Deleted subdomain records for: {$domain->domain_name}");

                // Delete mailboxes and aliases (cascade will handle this, but explicit for logging)
                if ($domain->mailboxes()->exists()) {
                    $mailboxCount = $domain->mailboxes()->count();
                    $domain->mailboxes()->delete();
                    Log::info("Deleted {$mailboxCount} mailbox records for: {$domain->domain_name}");
                }

                if ($domain->mailAliases()->exists()) {
                    $aliasCount = $domain->mailAliases()->count();
                    $domain->mailAliases()->delete();
                    Log::info("Deleted {$aliasCount} alias records for: {$domain->domain_name}");
                }

                // Delete SSL certificate record but keep files on disk for reuse
                if ($domain->sslCertificate) {
                    Log::info("Keeping SSL certificate files for potential reuse: {$domain->domain_name}");
                    $domain->sslCertificate->delete();
                }

                // Delete PHP-FPM pool record (config will be deleted by job)
                if ($domain->phpFpmPool) {
                    $domain->phpFpmPool->delete();
                }

                // Delete Nginx config record (file will be deleted by job)
                if ($domain->nginxConfig) {
                    $domain->nginxConfig->delete();
                }

                // Delete database records from panel (MySQL databases will be deleted by job)
                $domain->databases()->delete();
                
                // Delete domain record
                $domain->delete();
                Log::info("Domain record deleted from database: {$domain->domain_name}");
            });

            // Dispatch async job to clean up files, configs, and actual MySQL databases
            \App\Jobs\DeleteDomainJob::dispatch(
                $domainName,
                $documentRoot,
                $phpVersion,
                $phpFpmPool,
                $databases,
                $subdomainIds,
                $hasMailboxes
            )->delay(now()->addSeconds(2));

            Log::info("Cleanup job dispatched for: {$domainName}");
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete domain', [
                'domain' => $domainName ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
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

        // Note: SSL certificate should be issued manually via the "Issue SSL" button
        // to avoid Let's Encrypt rate limits
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
