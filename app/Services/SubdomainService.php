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
        $subdomain = Subdomain::create([
            'parent_domain_id' => $parentDomain->id,
            'subdomain_name' => $data['subdomain_name'],
            'document_root' => $data['document_root'] ?? $this->getDefaultDocumentRoot($parentDomain, $data['subdomain_name']),
            'php_version' => $data['php_version'] ?? $parentDomain->php_version,
            'ssl_enabled' => $data['ssl_enabled'] ?? false,
        ]);

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
        if (!File::exists($subdomain->document_root)) {
            File::makeDirectory($subdomain->document_root, 0755, true);
        }

        // Create default index.html
        $indexPath = $subdomain->document_root . '/index.html';
        if (!File::exists($indexPath)) {
            File::put($indexPath, $this->getDefaultIndexContent($subdomain->full_domain));
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

        $subdomain->update($data);

        // Regenerate Nginx config
        $nginxConfig = $this->nginxService->generateSubdomainConfig($subdomain);
        $this->nginxService->writeConfig($subdomain->full_domain, $nginxConfig);

        // Handle PHP version change
        if (isset($data['php_version']) && $data['php_version'] !== $oldPhpVersion) {
            // Reload both PHP-FPM services
            $this->phpFpmService->reload($oldPhpVersion);
            $this->phpFpmService->reload($subdomain->php_version);
        }

        return $subdomain->fresh();
    }

    /**
     * Delete subdomain
     */
    public function deleteSubdomain(Subdomain $subdomain): bool
    {
        // Remove Nginx config
        $this->nginxService->removeConfig($subdomain->full_domain);

        // Delete subdomain record
        return $subdomain->delete();
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
