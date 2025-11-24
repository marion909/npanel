<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Subdomain;
use App\Models\NginxConfigLog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class NginxService
{
    protected string $sitesAvailable;
    protected string $sitesEnabled;
    protected string $templatePath;

    public function __construct()
    {
        $this->sitesAvailable = env('NGINX_SITES_AVAILABLE', '/etc/nginx/sites-available');
        $this->sitesEnabled = env('NGINX_SITES_ENABLED', '/etc/nginx/sites-enabled');
        $this->templatePath = env('NGINX_TEMPLATE_PATH', resource_path('nginx'));
    }

    protected function renderTemplate(string $template, array $vars): string
    {
        $path = $this->templatePath . '/' . $template . '.stub';
        if (!File::exists($path)) {
            return '# Missing template: ' . $template;
        }
        $content = File::get($path);
        foreach ($vars as $k => $v) {
            $content = str_replace('{{ ' . $k . ' }}', $v, $content);
        }
        return $content;
    }

    public function buildDomainConfig(Domain $domain): string
    {
        return $this->renderTemplate('domain', [
            'server_name' => $domain->name,
            'root' => $domain->document_root,
            'php_socket' => sprintf('%s/php%s-fpm.sock', env('PHP_FPM_SOCKETS_PATH', '/run/php'), $domain->php_version),
        ]);
    }

    public function buildSubdomainConfig(Subdomain $subdomain): string
    {
        return $this->renderTemplate('subdomain', [
            'server_name' => $subdomain->full_name,
            'root' => $subdomain->effectiveDocumentRoot(),
            'php_socket' => sprintf('%s/php%s-fpm.sock', env('PHP_FPM_SOCKETS_PATH', '/run/php'), $subdomain->effectivePhpVersion()),
        ]);
    }

    protected function writeConfig(string $filename, string $content): void
    {
        if (!File::isDirectory($this->sitesAvailable)) {
            File::makeDirectory($this->sitesAvailable, 0755, true);
        }
        File::put($this->sitesAvailable . '/' . $filename, $content);
    }

    protected function symlinkConfig(string $filename): void
    {
        // On Windows dev environment we skip actual symlink.
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return;
        }
        $target = $this->sitesEnabled . '/' . $filename;
        $source = $this->sitesAvailable . '/' . $filename;
        if (file_exists($target)) {
            return;
        }
        @symlink($source, $target);
    }

    public function deployDomain(Domain $domain): void
    {
        $newConfig = $this->buildDomainConfig($domain);
        $filename = $domain->name . '.conf';
        $previous = null;
        $path = $this->sitesAvailable . '/' . $filename;
        if (file_exists($path)) {
            $previous = file_get_contents($path);
        }
        $this->writeConfig($filename, $newConfig);
        $this->symlinkConfig($filename);
        NginxConfigLog::create([
            'domain_id' => $domain->id,
            'action' => $previous ? 'update' : 'create',
            'previous_config' => $previous,
            'new_config' => $newConfig,
            'success' => true,
            'message' => 'Config written',
        ]);
    }

    public function deploySubdomain(Subdomain $subdomain): void
    {
        $newConfig = $this->buildSubdomainConfig($subdomain);
        $filename = $subdomain->full_name . '.conf';
        $previous = null;
        $path = $this->sitesAvailable . '/' . $filename;
        if (file_exists($path)) {
            $previous = file_get_contents($path);
        }
        $this->writeConfig($filename, $newConfig);
        $this->symlinkConfig($filename);
        NginxConfigLog::create([
            'domain_id' => $subdomain->domain_id,
            'subdomain_id' => $subdomain->id,
            'action' => $previous ? 'update' : 'create',
            'previous_config' => $previous,
            'new_config' => $newConfig,
            'success' => true,
            'message' => 'Config written',
        ]);
    }
}
