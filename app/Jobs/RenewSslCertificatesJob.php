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

class RenewSslCertificatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2;

    /**
     * Execute the job.
     */
    public function handle(SSLService $sslService): void
    {
        Log::info("Starting SSL certificate renewal check");

        // Get certificates expiring in next 30 days
        $expiringCertificates = $sslService->getExpiringCertificates(30);

        Log::info("Found {$expiringCertificates->count()} certificates to renew");

        foreach ($expiringCertificates as $domain) {
            try {
                Log::info("Renewing certificate for: {$domain->domain_name}");

                $success = $sslService->renewCertificate($domain);

                if ($success) {
                    Log::info("Certificate renewed successfully for: {$domain->domain_name}");
                } else {
                    Log::warning("Certificate renewal failed for: {$domain->domain_name}");
                }
            } catch (\Exception $e) {
                Log::error("Error renewing certificate for: {$domain->domain_name}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Reload Nginx after all renewals
        try {
            app(\App\Services\NginxService::class)->reload();
            Log::info("Nginx reloaded after certificate renewals");
        } catch (\Exception $e) {
            Log::error("Failed to reload Nginx after renewals", [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info("SSL certificate renewal check completed");
    }
}
