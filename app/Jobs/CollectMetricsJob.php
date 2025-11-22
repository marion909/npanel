<?php

namespace App\Jobs;

use App\Services\SystemMonitorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CollectMetricsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(SystemMonitorService $monitorService): void
    {
        try {
            // Collect all system metrics
            $metrics = $monitorService->getAllMetrics();
            
            // Store metrics in database
            $monitorService->storeMetrics($metrics);
            
            // Check for threshold violations and log alerts
            $alerts = $monitorService->checkThresholds($metrics);
            
            if (count($alerts) > 0) {
                foreach ($alerts as $alert) {
                    Log::warning('System monitoring alert: ' . $alert['message'], [
                        'metric' => $alert['metric'],
                        'value' => $alert['value'],
                        'threshold' => $alert['threshold'],
                        'level' => $alert['level'],
                    ]);
                }
            }
            
            Log::info('Metrics collected successfully', [
                'cpu' => $metrics['cpu'],
                'memory' => $metrics['memory']['percentage'],
                'disk' => $metrics['disk']['percentage'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to collect metrics: ' . $e->getMessage());
            throw $e;
        }
    }
}

