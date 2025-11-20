<?php

namespace App\Jobs;

use App\Models\Domain;
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
        public Domain $domain
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SSLService $sslService): void
    {
        try {
            Log::info("Issuing SSL certificate for: {$this->domain->domain_name}");

            // Check if DNS verification is required
            if (config('npanel.verify_dns_before_ssl')) {
                if (!$sslService->verifyDns($this->domain->domain_name)) {
                    Log::warning("DNS not pointing to server for: {$this->domain->domain_name}");
                    
                    // Retry later
                    $this->release(config('npanel.dns_propagation_wait'));
                    return;
                }
            }

            // Issue certificate
            $certificate = $sslService->issueCertificate($this->domain);

            Log::info("SSL certificate issued successfully for: {$this->domain->domain_name}", [
                'expiry_date' => $certificate->expiry_date->toDateTimeString(),
            ]);

            // Reload Nginx to apply SSL configuration
            app(\App\Services\NginxService::class)->reload();

        } catch (\Exception $e) {
            Log::error("Failed to issue SSL certificate for: {$this->domain->domain_name}", [
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
        Log::error("SSL certificate issuance failed permanently for: {$this->domain->domain_name}", [
            'error' => $exception->getMessage(),
        ]);

        // Disable SSL on domain
        $this->domain->update([
            'ssl_enabled' => false,
            'ssl_cert_path' => null,
            'ssl_key_path' => null,
        ]);
    }
}
