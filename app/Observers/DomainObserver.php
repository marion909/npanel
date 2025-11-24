<?php

namespace App\Observers;

use App\Models\Domain;
use App\Services\DocumentRootService;
use App\Services\HetznerDnsService;
use App\Services\WildcardSslService;
use App\Services\DnsValidationService;
use App\Services\NginxService;
use Illuminate\Support\Facades\Log;

class DomainObserver
{
    public function creating(Domain $domain): void
    {
        // Ensure document root default before creation
        if (!$domain->document_root) {
            $domain->document_root = rtrim(env('DOCUMENT_ROOT_BASE', '/var/www'), '/') . '/' . $domain->name;
        }
        // Default status
        if (!$domain->status) {
            $domain->status = 'pending';
        }
    }

    public function created(Domain $domain): void
    {
        $rootService = app(DocumentRootService::class);
        $dnsService = app(HetznerDnsService::class);
        $validationService = app(DnsValidationService::class);
        $nginxService = app(NginxService::class);
        $sslService = app(WildcardSslService::class);

        $rootService->ensureDomainRoot($domain);

        // Resolve Hetzner zone id if token available
        if (!$domain->hetzner_zone_id && env('HETZNER_DNS_API_TOKEN')) {
            $zoneId = $dnsService->findZoneIdForDomain($domain->name);
            if ($zoneId) {
                $domain->hetzner_zone_id = $zoneId;
                $domain->save();
            }
        }

        // Create verification TXT record
        $validationService->generateToken($domain);
        if ($domain->hetzner_zone_id) {
            $dnsService->createVerificationTxtRecord($domain);
        }

        // Ensure A record (placeholder IP)
        $serverIp = env('APP_SERVER_IPV4', '127.0.0.1');
        if ($domain->hetzner_zone_id) {
            $dnsService->ensureARecord($domain, $serverIp);
        }

        // Deploy nginx config
        $nginxService->deployDomain($domain);

        // Request wildcard SSL if enabled
        if ($domain->wildcard_ssl_enabled) {
            $sslService->requestWildcardCertificate($domain);
        }
    }

    public function updating(Domain $domain): void
    {
        // Track if critical fields changed for later redeploy
        foreach (['php_version','document_root','wildcard_ssl_enabled'] as $field) {
            if ($domain->isDirty($field)) {
                $domain->setAttribute('_needs_redeploy', true);
            }
        }
    }

    public function updated(Domain $domain): void
    {
        $nginxService = app(NginxService::class);
        $sslService = app(WildcardSslService::class);
        $dnsService = app(HetznerDnsService::class);

        // Redeploy config if needed
        if ($domain->getAttribute('_needs_redeploy')) {
            $nginxService->deployDomain($domain);
        }

        // If SSL enabled toggled on
        if ($domain->wasChanged('wildcard_ssl_enabled') && $domain->wildcard_ssl_enabled) {
            $sslService->requestWildcardCertificate($domain);
        }

        // Re-ensure A record if php_version or document_root changes (not strictly needed, but safe for rebuild flows)
        if ($domain->wasChanged('php_version') || $domain->wasChanged('document_root')) {
            if ($domain->hetzner_zone_id) {
                $dnsService->ensureARecord($domain, env('APP_SERVER_IPV4', '127.0.0.1'));
            }
        }
    }

    public function deleting(Domain $domain): void
    {
        // Placeholder: could remove nginx config or create a log entry.
        Log::info('Domain deleting: ' . $domain->name);
    }
}
