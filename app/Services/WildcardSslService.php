<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WildcardSslService
{
    protected string $email;
    protected string $provider;

    public function __construct()
    {
        $this->email = env('WILDCARD_SSL_EMAIL', 'admin@example.com');
        $this->provider = env('WILDCARD_SSL_PROVIDER', 'letsencrypt');
    }

    public function certificatePath(Domain $domain): string
    {
        return '/etc/letsencrypt/live/' . $domain->name;
    }

    public function hasValidCertificate(Domain $domain): bool
    {
        // Placeholder: would inspect filesystem / expiry metadata
        return $domain->wildcard_ssl_status === 'issued';
    }

    public function requestWildcardCertificate(Domain $domain): bool
    {
        // Placeholder: integrate with certbot dns-hetzner plugin via Artisan command / Process
        Log::info('Requesting wildcard certificate for ' . $domain->name);
        $domain->wildcard_ssl_status = 'issued';
        $domain->wildcard_ssl_last_issued_at = now();
        $domain->save();
        return true;
    }

    public function renewWildcardCertificate(Domain $domain): bool
    {
        Log::info('Renewing wildcard certificate for ' . $domain->name);
        $domain->wildcard_ssl_status = 'issued';
        $domain->wildcard_ssl_last_issued_at = now();
        $domain->save();
        return true;
    }

    public function revokeCertificate(Domain $domain): bool
    {
        Log::warning('Revoking wildcard certificate for ' . $domain->name);
        $domain->wildcard_ssl_status = 'revoked';
        $domain->save();
        return true;
    }
}
