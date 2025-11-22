<?php

namespace App\Http\Controllers;

use App\Services\SystemMonitorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MonitoringController extends Controller
{
    protected SystemMonitorService $monitorService;

    public function __construct(SystemMonitorService $monitorService)
    {
        $this->monitorService = $monitorService;
    }

    /**
     * Display the monitoring dashboard
     */
    public function index()
    {
        $metrics = $this->monitorService->getAllMetrics();
        $alerts = $this->monitorService->checkThresholds($metrics);

        return Inertia::render('Monitoring/Index', [
            'initialMetrics' => $metrics,
            'alerts' => $alerts,
        ]);
    }

    /**
     * Get current system metrics (API endpoint for real-time updates)
     */
    public function stats()
    {
        $metrics = $this->monitorService->getAllMetrics();
        $alerts = $this->monitorService->checkThresholds($metrics);

        return response()->json([
            'metrics' => $metrics,
            'alerts' => $alerts,
        ]);
    }

    /**
     * Get historical metrics
     */
    public function history(Request $request)
    {
        $hours = $request->input('hours', 24);
        $history = $this->monitorService->getHistoricalMetrics($hours);

        return response()->json([
            'history' => $history,
        ]);
    }
}
