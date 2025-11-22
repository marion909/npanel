<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\Subdomain;
use App\Services\SSLService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IssueSslCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 300; // 5 minutes between retries

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Domain|Subdomain $domainOrSubdomain
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SSLService $sslService): void
    {
        try {
            $isSubdomain = $this->domainOrSubdomain instanceof Subdomain;
            $domainName = $isSubdomain 
                ? $this->domainOrSubdomain->full_domain 
                : $this->domainOrSubdomain->domain_name;

            Log::info("Issuing SSL certificate for: {$domainName}");

            // Check if DNS verification is required
            if (config('npanel.verify_dns_before_ssl')) {
                if (!$sslService->verifyDns($domainName)) {
                    Log::warning("DNS not pointing to server for: {$domainName}");
                    
                    // Retry later
                    $this->release(config('npanel.dns_propagation_wait'));
                    return;
                }
            }

            // Issue certificate
            $certificate = $sslService->issueCertificate($this->domainOrSubdomain);

            Log::info("SSL certificate issued successfully for: {$domainName}", [
                'expiry_date' => $certificate->expiry_date->toDateTimeString(),
            ]);

            // Reload Nginx to apply SSL configuration
            app(\App\Services\NginxService::class)->reload();

        } catch (\Exception $e) {
            $domainName = $this->domainOrSubdomain instanceof Subdomain 
                ? $this->domainOrSubdomain->full_domain 
                : $this->domainOrSubdomain->domain_name;

            Log::error("Failed to issue SSL certificate for: {$domainName}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $domainName = $this->domainOrSubdomain instanceof Subdomain 
            ? $this->domainOrSubdomain->full_domain 
            : $this->domainOrSubdomain->domain_name;

        Log::error("SSL certificate issuance failed permanently for: {$domainName}", [
            'error' => $exception->getMessage(),
        ]);

        // Disable SSL
        $this->domainOrSubdomain->update([
            'ssl_enabled' => false,
            'ssl_cert_path' => null,
            'ssl_key_path' => null,
        ]);
    }
}
