<?php

namespace App\Jobs;

use App\Services\NginxService;
use App\Services\PhpFpmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReloadServicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $phpVersion = null,
        public bool $reloadNginx = true,
        public bool $reloadPhpFpm = true
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NginxService $nginxService, PhpFpmService $phpFpmService): void
    {
        // Small delay to ensure the current request completes
        sleep(2);

        try {
            if ($this->reloadNginx) {
                $nginxService->reload();
                Log::info('Nginx reloaded successfully');
            }
        } catch (\Exception $e) {
            Log::warning('Nginx reload failed', [
                'error' => $e->getMessage()
            ]);
        }

        try {
            if ($this->reloadPhpFpm && $this->phpVersion) {
                $phpFpmService->reload($this->phpVersion);
                Log::info('PHP-FPM reloaded successfully', [
                    'php_version' => $this->phpVersion
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('PHP-FPM reload failed', [
                'php_version' => $this->phpVersion,
                'error' => $e->getMessage()
            ]);
        }
    }
}
