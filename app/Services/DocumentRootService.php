<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Subdomain;
use Illuminate\Support\Facades\File;

class DocumentRootService
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = rtrim(env('DOCUMENT_ROOT_BASE', '/var/www'), '/');
    }

    public function domainRoot(Domain $domain): string
    {
        return $domain->document_root ?: $this->basePath . '/' . $domain->name;
    }

    public function subdomainRoot(Subdomain $subdomain): string
    {
        return $subdomain->document_root ?: $this->domainRoot($subdomain->domain) . '/' . $subdomain->name;
    }

    public function ensureDomainRoot(Domain $domain): string
    {
        $path = $this->domainRoot($domain);
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }
        return $path;
    }

    public function ensureSubdomainRoot(Subdomain $subdomain): string
    {
        $path = $this->subdomainRoot($subdomain);
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }
        return $path;
    }
}
