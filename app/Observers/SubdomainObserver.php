<?php

namespace App\Observers;

use App\Models\Subdomain;
use App\Services\DocumentRootService;
use App\Services\NginxService;
use App\Services\HetznerDnsService;
use Illuminate\Support\Facades\Log;

class SubdomainObserver
{
    public function creating(Subdomain $subdomain): void
    {
        if ($subdomain->domain && !$subdomain->full_name) {
            $subdomain->full_name = $subdomain->name . '.' . $subdomain->domain->name;
        }
    }

    public function created(Subdomain $subdomain): void
    {
        $rootService = app(DocumentRootService::class);
        $nginxService = app(NginxService::class);
        $dnsService = app(HetznerDnsService::class);

        $rootService->ensureSubdomainRoot($subdomain);

        if ($subdomain->domain->hetzner_zone_id) {
            // Add A record for subdomain
            $dnsService->createRecord($subdomain->domain, [
                'type' => 'A',
                'name' => $subdomain->full_name,
                'value' => env('APP_SERVER_IPV4', '127.0.0.1'),
                'ttl' => 3600,
            ]);
        }

        if ($subdomain->nginx_enabled) {
            $nginxService->deploySubdomain($subdomain);
        }
    }

    public function updating(Subdomain $subdomain): void
    {
        foreach (['php_version','document_root','nginx_enabled'] as $field) {
            if ($subdomain->isDirty($field)) {
                $subdomain->setAttribute('_needs_redeploy', true);
            }
        }
    }

    public function updated(Subdomain $subdomain): void
    {
        $nginxService = app(NginxService::class);
        $rootService = app(DocumentRootService::class);

        if ($subdomain->getAttribute('_needs_redeploy')) {
            $rootService->ensureSubdomainRoot($subdomain);
            if ($subdomain->nginx_enabled) {
                $nginxService->deploySubdomain($subdomain);
            }
        }
    }

    public function deleting(Subdomain $subdomain): void
    {
        Log::info('Subdomain deleting: ' . $subdomain->full_name);
        // Could remove nginx config or create a log entry.
    }
}
