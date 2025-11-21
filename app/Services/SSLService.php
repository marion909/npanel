<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\SslCertificate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SSLService
{
    protected string $acmeShPath;
    protected string $certBasePath;

    public function __construct()
    {
        $this->acmeShPath = config('npanel.acme_sh_path');
        $this->certBasePath = config('npanel.ssl_cert_base_path');
    }

    /**
     * Issue SSL certificate for domain using Let's Encrypt
     */
    public function issueCertificate(Domain $domain): SslCertificate
    {
        // Check if certificate already exists and is valid
        $existingCert = $this->checkExistingCertificate($domain->domain_name);
        if ($existingCert) {
            Log::info("Reusing existing SSL certificate for {$domain->domain_name}");
            return $this->applyCertificate($domain, $existingCert);
        }

        // Create ACME challenge directory
        $this->createAcmeChallenge($domain);

        // Build acme.sh command
        $domains = $this->getDomainList($domain);
        $webroot = $domain->document_root;

        $command = $this->buildAcmeCommand($domains, $webroot);

        // Execute certificate issuance
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMsg = implode("\n", $output);
            Log::error("SSL certificate issuance failed for {$domain->domain_name}", [
                'output' => $errorMsg,
                'return_code' => $returnCode,
            ]);
            throw new \Exception("Failed to issue SSL certificate: " . $errorMsg);
        }

        // Install certificate
        $this->installCertificate($domain);

        // Create or update SSL certificate record
        $certificate = SslCertificate::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'certificate_path' => $this->getCertPath($domain->domain_name),
                'private_key_path' => $this->getKeyPath($domain->domain_name),
                'chain_path' => $this->getChainPath($domain->domain_name),
                'provider' => 'letsencrypt',
                'issue_date' => now(),
                'expiry_date' => now()->addDays(90),
                'auto_renew' => true,
            ]
        );

        // Update domain with SSL paths
        $domain->update([
            'ssl_enabled' => true,
            'ssl_cert_path' => $certificate->certificate_path,
            'ssl_key_path' => $certificate->private_key_path,
            'ssl_expiry_date' => $certificate->expiry_date,
        ]);

        // Regenerate Nginx config with SSL
        app(NginxService::class)->writeConfig(
            $domain->domain_name,
            app(NginxService::class)->generateDomainConfig($domain->fresh())
        );

        return $certificate;
    }

    /**
     * Check if existing valid certificate exists for domain
     */
    protected function checkExistingCertificate(string $domainName): ?array
    {
        $certPath = $this->getCertPath($domainName);
        $keyPath = $this->getKeyPath($domainName);
        $chainPath = $this->getChainPath($domainName);

        // Check if files exist
        if (!File::exists($certPath) || !File::exists($keyPath)) {
            return null;
        }

        // Check if certificate is still valid (not expired)
        try {
            $certData = openssl_x509_parse(File::get($certPath));
            if (!$certData) {
                return null;
            }

            $expiryDate = \Carbon\Carbon::createFromTimestamp($certData['validTo_time_t']);
            
            // Certificate must be valid for at least 30 more days
            if ($expiryDate->lt(now()->addDays(30))) {
                Log::info("Existing certificate for {$domainName} expires soon, will issue new one");
                return null;
            }

            Log::info("Found valid existing certificate for {$domainName}", [
                'expiry' => $expiryDate->toDateTimeString()
            ]);

            return [
                'certificate_path' => $certPath,
                'private_key_path' => $keyPath,
                'chain_path' => $chainPath,
                'expiry_date' => $expiryDate,
            ];
        } catch (\Exception $e) {
            Log::warning("Failed to parse existing certificate for {$domainName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Apply existing certificate to domain
     */
    protected function applyCertificate(Domain $domain, array $certData): SslCertificate
    {
        // Create or update SSL certificate record
        $certificate = SslCertificate::updateOrCreate(
            ['domain_id' => $domain->id],
            [
                'certificate_path' => $certData['certificate_path'],
                'private_key_path' => $certData['private_key_path'],
                'chain_path' => $certData['chain_path'],
                'provider' => 'letsencrypt',
                'issue_date' => now(),
                'expiry_date' => $certData['expiry_date'],
                'auto_renew' => true,
            ]
        );

        // Update domain with SSL paths
        $domain->update([
            'ssl_enabled' => true,
            'ssl_cert_path' => $certificate->certificate_path,
            'ssl_key_path' => $certificate->private_key_path,
            'ssl_expiry_date' => $certificate->expiry_date,
        ]);

        // Regenerate Nginx config with SSL
        app(NginxService::class)->writeConfig(
            $domain->domain_name,
            app(NginxService::class)->generateDomainConfig($domain->fresh())
        );

        return $certificate;
    }

    /**
     * Get list of domains for certificate (main + www + @)
     */
    protected function getDomainList(Domain $domain): array
    {
        return [
            $domain->domain_name,
            'www.' . $domain->domain_name,
        ];
    }

    /**
     * Build acme.sh command for certificate issuance
     */
    protected function buildAcmeCommand(array $domains, string $webroot): string
    {
        $domainFlags = array_map(fn($d) => "-d {$d}", $domains);
        $domainString = implode(' ', $domainFlags);

        return sprintf(
            '%s --issue %s -w %s --server letsencrypt',
            $this->acmeShPath,
            $domainString,
            $webroot
        );
    }

    /**
     * Create .well-known/acme-challenge directory for HTTP-01 validation
     */
    protected function createAcmeChallenge(Domain $domain): void
    {
        $challengeDir = $domain->document_root . '/.well-known/acme-challenge';

        if (!File::exists($challengeDir)) {
            File::makeDirectory($challengeDir, 0755, true);
        }
    }

    /**
     * Install certificate to system location
     */
    protected function installCertificate(Domain $domain): void
    {
        $domainName = $domain->domain_name;
        $certDir = $this->certBasePath . '/' . $domainName;

        // Create certificate directory
        if (!File::exists($certDir)) {
            File::makeDirectory($certDir, 0755, true);
        }

        // Install certificate using acme.sh
        $command = sprintf(
            '%s --install-cert -d %s --cert-file %s --key-file %s --fullchain-file %s',
            $this->acmeShPath,
            $domainName,
            $this->getCertPath($domainName),
            $this->getKeyPath($domainName),
            $this->getChainPath($domainName)
        );

        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Failed to install certificate: " . implode("\n", $output));
        }
    }

    /**
     * Renew SSL certificate
     */
    public function renewCertificate(Domain $domain): bool
    {
        $command = sprintf(
            '%s --renew -d %s --force',
            $this->acmeShPath,
            $domain->domain_name
        );

        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            // Update certificate record
            $certificate = $domain->sslCertificate;
            if ($certificate) {
                $certificate->update([
                    'issue_date' => now(),
                    'expiry_date' => now()->addDays(90),
                    'last_renewal_attempt' => now(),
                ]);

                $domain->update(['ssl_expiry_date' => $certificate->expiry_date]);
            }

            return true;
        }

        Log::error("SSL certificate renewal failed for {$domain->domain_name}", [
            'output' => implode("\n", $output),
        ]);

        return false;
    }

    /**
     * Revoke SSL certificate
     */
    public function revokeCertificate(Domain $domain): bool
    {
        $command = sprintf(
            '%s --revoke -d %s',
            $this->acmeShPath,
            $domain->domain_name
        );

        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            // Delete certificate record
            if ($domain->sslCertificate) {
                $domain->sslCertificate->delete();
            }

            // Update domain
            $domain->update([
                'ssl_enabled' => false,
                'ssl_cert_path' => null,
                'ssl_key_path' => null,
                'ssl_expiry_date' => null,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Check if certificate is expiring soon
     */
    public function isExpiringSoon(Domain $domain, int $days = 30): bool
    {
        if (!$domain->ssl_enabled || !$domain->ssl_expiry_date) {
            return false;
        }

        return $domain->ssl_expiry_date->diffInDays(now()) <= $days;
    }

    /**
     * Get expiring certificates
     */
    public function getExpiringCertificates(int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return Domain::where('ssl_enabled', true)
            ->whereNotNull('ssl_expiry_date')
            ->where('ssl_expiry_date', '<=', now()->addDays($days))
            ->with('sslCertificate')
            ->get();
    }

    /**
     * Get certificate file path
     */
    protected function getCertPath(string $domainName): string
    {
        return $this->certBasePath . '/' . $domainName . '/cert.pem';
    }

    /**
     * Get private key file path
     */
    protected function getKeyPath(string $domainName): string
    {
        return $this->certBasePath . '/' . $domainName . '/privkey.pem';
    }

    /**
     * Get chain file path
     */
    protected function getChainPath(string $domainName): string
    {
        return $this->certBasePath . '/' . $domainName . '/fullchain.pem';
    }

    /**
     * Verify domain DNS points to server
     */
    public function verifyDns(string $domainName): bool
    {
        $serverIp = gethostbyname(gethostname());
        $domainIp = gethostbyname($domainName);

        return $serverIp === $domainIp;
    }
}
