<?php

namespace App\Jobs;

use App\Models\Subdomain;
use App\Services\WordPressService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InstallWordPressJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subdomain $subdomain
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WordPressService $wpService): void
    {
        try {
            Log::info('WordPress installation job started', [
                'subdomain_id' => $this->subdomain->id,
            ]);

            $result = $wpService->installWordPress($this->subdomain);

            // Refresh subdomain model and mark as having WordPress installed
            $this->subdomain->refresh();
            $this->subdomain->wordpress_installed = true;
            $this->subdomain->save();

            // Store credentials in cache for 24 hours
            $cacheKey = 'wordpress_credentials_' . $this->subdomain->id;
            Cache::put($cacheKey, $result['credentials'], 86400);

            Log::info('WordPress installation job completed', [
                'subdomain_id' => $this->subdomain->id,
                'cache_key' => $cacheKey,
            ]);

        } catch (\Exception $e) {
            Log::error('WordPress installation job failed', [
                'subdomain_id' => $this->subdomain->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
