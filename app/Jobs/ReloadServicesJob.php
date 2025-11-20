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
        public string|array|null $phpVersions = null,
        public bool $reloadNginx = true,
        public bool $reloadPhpFpm = true
    ) {
        // Normalize to array
        if (is_string($this->phpVersions)) {
            $this->phpVersions = [$this->phpVersions];
        }
    }

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
            if ($this->reloadPhpFpm && $this->phpVersions) {
                foreach ((array)$this->phpVersions as $version) {
                    if ($version) {
                        $phpFpmService->reload($version);
                        Log::info('PHP-FPM reloaded successfully', [
                            'php_version' => $version
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('PHP-FPM reload failed', [
                'php_versions' => $this->phpVersions,
                'error' => $e->getMessage()
            ]);
        }
    }
}
