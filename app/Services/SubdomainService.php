<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Subdomain;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SubdomainService
{
    public function __construct(
        protected NginxService $nginxService,
        protected PhpFpmService $phpFpmService
    ) {}

    /**
     * Create a new subdomain
     */
    public function createSubdomain(Domain $parentDomain, array $data): Subdomain
    {
        $subdomain = Subdomain::firstOrCreate(
            [
                'parent_domain_id' => $parentDomain->id,
                'subdomain_name' => $data['subdomain_name'],
            ],
            [
                'document_root' => $data['document_root'] ?? $this->getDefaultDocumentRoot($parentDomain, $data['subdomain_name']),
                'php_version' => $data['php_version'] ?? $parentDomain->php_version,
                // Auto-enable SSL if parent domain has SSL
                'ssl_enabled' => $data['ssl_enabled'] ?? $parentDomain->ssl_enabled,
            ]
        );

        // Create directory structure
        $this->createDirectoryStructure($subdomain);

        // Generate Nginx configuration
        $nginxConfig = $this->nginxService->generateSubdomainConfig($subdomain);
        $configPath = $this->nginxService->writeConfig($subdomain->full_domain, $nginxConfig);
        $subdomain->update(['nginx_config_path' => $configPath]);

        // Enable site
        $this->nginxService->enableSite($subdomain->full_domain);

        // Create PHP-FPM pool if different version than parent
        if ($subdomain->php_version !== $parentDomain->php_version) {
            $this->createPhpFpmPool($subdomain, $parentDomain);
        }

        // Issue SSL certificate if parent has SSL enabled
        if ($subdomain->ssl_enabled) {
            \App\Jobs\IssueSslCertificateJob::dispatch($subdomain);
        }

        // Dispatch async reload job
        \App\Jobs\ReloadServicesJob::dispatch([$subdomain->php_version], 2);

        return $subdomain;
    }

    /**
     * Get default document root for subdomain
     */
    protected function getDefaultDocumentRoot(Domain $parentDomain, string $subdomainName): string
    {
        $baseDir = dirname($parentDomain->document_root);
        return "{$baseDir}/subdomains/{$subdomainName}";
    }

    /**
     * Create directory structure for subdomain
     */
    protected function createDirectoryStructure(Subdomain $subdomain): void
    {
        // Always create directory if it doesn't exist
        if (!File::exists($subdomain->document_root)) {
            File::makeDirectory($subdomain->document_root, 0755, true);
            
            // Set ownership to www-data
            exec("sudo chown -R www-data:www-data " . escapeshellarg($subdomain->document_root));
            
            // Create default index.html only if directory was just created
            $indexPath = $subdomain->document_root . '/index.html';
            if (!File::exists($indexPath)) {
                File::put($indexPath, $this->getDefaultIndexContent($subdomain->full_domain));
            }
        } else {
            // Directory exists (e.g., subfolder of main domain), don't create index.html
            // Just ensure proper ownership
            exec("sudo chown -R www-data:www-data " . escapeshellarg($subdomain->document_root));
        }
    }

    /**
     * Get default index.html content
     */
    protected function getDefaultIndexContent(string $fullDomain): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {$fullDomain}</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #333; }
        p { color: #666; }
    </style>
</head>
<body>
    <h1>Welcome to {$fullDomain}</h1>
    <p>Your subdomain has been successfully configured!</p>
</body>
</html>
HTML;
    }

    /**
     * Create PHP-FPM pool for subdomain
     */
    protected function createPhpFpmPool(Subdomain $subdomain, Domain $parentDomain): void
    {
        $poolName = Str::slug($subdomain->full_domain);
        $socketPath = config('npanel.php_fpm_socket_dir') . '/php' . $subdomain->php_version . '-fpm-' . $poolName . '.sock';

        $subdomain->update(['php_fpm_pool' => $poolName]);
    }

    /**
     * Update subdomain
     */
    public function updateSubdomain(Subdomain $subdomain, array $data): Subdomain
    {
        $oldPhpVersion = $subdomain->php_version;
        $oldDocumentRoot = $subdomain->document_root;

        $subdomain->update($data);

        // If document_root changed, ensure directory exists
        if (isset($data['document_root']) && $data['document_root'] !== $oldDocumentRoot) {
            $this->createDirectoryStructure($subdomain);
        }

        // Regenerate Nginx config
        $nginxConfig = $this->nginxService->generateSubdomainConfig($subdomain);
        $this->nginxService->writeConfig($subdomain->full_domain, $nginxConfig);

        // Dispatch async reload job
        \App\Jobs\ReloadServicesJob::dispatch($oldPhpVersion !== $subdomain->php_version ? [$oldPhpVersion, $subdomain->php_version] : null, 2);

        return $subdomain->fresh();
    }

    /**
     * Delete subdomain
     */
    public function deleteSubdomain(Subdomain $subdomain): bool
    {
        $phpVersion = $subdomain->php_version;
        
        // Remove Nginx config
        $this->nginxService->removeConfig($subdomain->full_domain);

        // Delete subdomain record
        $result = $subdomain->delete();

        // Dispatch async reload job
        \App\Jobs\ReloadServicesJob::dispatch([$phpVersion], 2);

        return $result;
    }

    /**
     * Activate subdomain (reload services)
     */
    public function activateSubdomain(Subdomain $subdomain): void
    {
        // Test and reload Nginx
        $nginxTest = $this->nginxService->testConfig();
        if (!$nginxTest['success']) {
            throw new \Exception("Nginx configuration test failed: " . $nginxTest['output']);
        }

        $this->nginxService->reload();

        // Reload PHP-FPM if custom version
        if ($subdomain->php_version) {
            $this->phpFpmService->reload($subdomain->php_version);
        }
    }
}
