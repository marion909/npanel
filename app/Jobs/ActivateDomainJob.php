<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Services\DomainService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ActivateDomainJob implements ShouldQueue
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
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Domain $domain
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DomainService $domainService): void
    {
        try {
            Log::info("Activating domain: {$this->domain->domain_name}");

            // Activate domain (test configs, reload services)
            $domainService->activateDomain($this->domain);

            Log::info("Domain activated successfully: {$this->domain->domain_name}");
        } catch (\Exception $e) {
            Log::error("Failed to activate domain: {$this->domain->domain_name}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update domain status to failed
            $this->domain->update(['status' => 'failed']);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Domain activation job failed permanently: {$this->domain->domain_name}", [
            'error' => $exception->getMessage(),
        ]);

        $this->domain->update(['status' => 'failed']);
    }
}
