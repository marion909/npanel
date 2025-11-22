<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SystemMonitorService
{
    /**
     * Get current CPU usage percentage
     */
    public function getCpuUsage(): float
    {
        if (!file_exists('/proc/stat')) {
            return 0.0;
        }

        // First measurement
        $stat1 = $this->parseCpuStat();
        usleep(100000); // 100ms delay
        $stat2 = $this->parseCpuStat();

        $idle1 = $stat1['idle'] + $stat1['iowait'];
        $idle2 = $stat2['idle'] + $stat2['iowait'];

        $total1 = array_sum($stat1);
        $total2 = array_sum($stat2);

        $totalDiff = $total2 - $total1;
        $idleDiff = $idle2 - $idle1;

        if ($totalDiff <= 0) {
            return 0.0;
        }

        return round((($totalDiff - $idleDiff) / $totalDiff) * 100, 2);
    }

    /**
     * Parse /proc/stat for CPU values
     */
    private function parseCpuStat(): array
    {
        $stat = file_get_contents('/proc/stat');
        preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/m', $stat, $matches);

        return [
            'user' => (int)($matches[1] ?? 0),
            'nice' => (int)($matches[2] ?? 0),
            'system' => (int)($matches[3] ?? 0),
            'idle' => (int)($matches[4] ?? 0),
            'iowait' => (int)($matches[5] ?? 0),
            'irq' => (int)($matches[6] ?? 0),
            'softirq' => (int)($matches[7] ?? 0),
        ];
    }

    /**
     * Get memory usage information
     */
    public function getMemoryUsage(): array
    {
        if (!file_exists('/proc/meminfo')) {
            return [
                'total' => 0,
                'used' => 0,
                'free' => 0,
                'cached' => 0,
                'percentage' => 0.0,
            ];
        }

        $meminfo = file_get_contents('/proc/meminfo');
        $lines = explode("\n", $meminfo);
        $data = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $matches)) {
                $data[$matches[1]] = (int)$matches[2]; // in KB
            }
        }

        $total = $data['MemTotal'] ?? 0;
        $free = $data['MemFree'] ?? 0;
        $buffers = $data['Buffers'] ?? 0;
        $cached = $data['Cached'] ?? 0;
        $sReclaimable = $data['SReclaimable'] ?? 0;

        // Available memory calculation (similar to 'free' command)
        $available = $free + $buffers + $cached + $sReclaimable;
        $used = $total - $available;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $available,
            'cached' => $cached,
            'percentage' => $total > 0 ? round(($used / $total) * 100, 2) : 0.0,
        ];
    }

    /**
     * Get disk usage for specified path
     */
    public function getDiskUsage(string $path = '/'): array
    {
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percentage' => $total > 0 ? round(($used / $total) * 100, 2) : 0.0,
        ];
    }

    /**
     * Get network interface statistics
     */
    public function getNetworkStats(): array
    {
        if (!file_exists('/proc/net/dev')) {
            return [];
        }

        $netdev = file_get_contents('/proc/net/dev');
        $lines = explode("\n", $netdev);
        $interfaces = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*(\w+):\s*(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/', $line, $matches)) {
                $interface = $matches[1];
                
                // Skip loopback
                if ($interface === 'lo') {
                    continue;
                }

                $interfaces[$interface] = [
                    'rx_bytes' => (int)$matches[2],
                    'tx_bytes' => (int)$matches[3],
                ];
            }
        }

        return $interfaces;
    }

    /**
     * Get system load averages
     */
    public function getLoadAverage(): array
    {
        if (!file_exists('/proc/loadavg')) {
            return [
                '1min' => 0.0,
                '5min' => 0.0,
                '15min' => 0.0,
            ];
        }

        $loadavg = file_get_contents('/proc/loadavg');
        $parts = explode(' ', $loadavg);

        return [
            '1min' => (float)($parts[0] ?? 0),
            '5min' => (float)($parts[1] ?? 0),
            '15min' => (float)($parts[2] ?? 0),
        ];
    }

    /**
     * Get system uptime in seconds
     */
    public function getUptime(): int
    {
        if (!file_exists('/proc/uptime')) {
            return 0;
        }

        $uptime = file_get_contents('/proc/uptime');
        $parts = explode(' ', $uptime);

        return (int)($parts[0] ?? 0);
    }

    /**
     * Get all PHP-FPM pool statistics
     */
    public function getPhpFpmStats(): array
    {
        $pools = DB::table('php_fpm_pools')->get();
        $stats = [];

        foreach ($pools as $pool) {
            $statusUrl = "http://127.0.0.1/php-fpm-status-{$pool->pool_name}?json";
            
            try {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 2,
                    ],
                ]);
                
                $json = @file_get_contents($statusUrl, false, $context);
                
                if ($json !== false) {
                    $data = json_decode($json, true);
                    
                    if ($data) {
                        $stats[$pool->pool_name] = [
                            'pool' => $pool->pool_name,
                            'php_version' => $pool->php_version,
                            'process_manager' => $data['process-manager'] ?? 'unknown',
                            'start_time' => $data['start-time'] ?? 0,
                            'accepted_conn' => $data['accepted-conn'] ?? 0,
                            'listen_queue' => $data['listen-queue'] ?? 0,
                            'max_listen_queue' => $data['max-listen-queue'] ?? 0,
                            'listen_queue_len' => $data['listen-queue-len'] ?? 0,
                            'idle_processes' => $data['idle-processes'] ?? 0,
                            'active_processes' => $data['active-processes'] ?? 0,
                            'total_processes' => $data['total-processes'] ?? 0,
                            'max_active_processes' => $data['max-active-processes'] ?? 0,
                            'max_children_reached' => $data['max-children-reached'] ?? 0,
                            'slow_requests' => $data['slow-requests'] ?? 0,
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Pool status not accessible
                $stats[$pool->pool_name] = [
                    'pool' => $pool->pool_name,
                    'php_version' => $pool->php_version,
                    'error' => 'Status not accessible',
                ];
            }
        }

        return $stats;
    }

    /**
     * Get Nginx statistics from stub_status
     */
    public function getNginxStats(): array
    {
        $statusUrl = "http://127.0.0.1/nginx-status";
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2,
                ],
            ]);
            
            $response = @file_get_contents($statusUrl, false, $context);
            
            if ($response !== false) {
                // Parse stub_status format:
                // Active connections: 1
                // server accepts handled requests
                //  123 123 456
                // Reading: 0 Writing: 1 Waiting: 0
                
                $stats = [
                    'active_connections' => 0,
                    'accepts' => 0,
                    'handled' => 0,
                    'requests' => 0,
                    'reading' => 0,
                    'writing' => 0,
                    'waiting' => 0,
                ];

                if (preg_match('/Active connections:\s+(\d+)/', $response, $matches)) {
                    $stats['active_connections'] = (int)$matches[1];
                }

                if (preg_match('/\s+(\d+)\s+(\d+)\s+(\d+)\s+/', $response, $matches)) {
                    $stats['accepts'] = (int)$matches[1];
                    $stats['handled'] = (int)$matches[2];
                    $stats['requests'] = (int)$matches[3];
                }

                if (preg_match('/Reading:\s+(\d+)\s+Writing:\s+(\d+)\s+Waiting:\s+(\d+)/', $response, $matches)) {
                    $stats['reading'] = (int)$matches[1];
                    $stats['writing'] = (int)$matches[2];
                    $stats['waiting'] = (int)$matches[3];
                }

                return $stats;
            }
        } catch (\Exception $e) {
            // Nginx status not accessible
        }

        return [
            'error' => 'Nginx status not accessible',
        ];
    }

    /**
     * Get comprehensive system metrics
     */
    public function getAllMetrics(): array
    {
        return [
            'cpu' => $this->getCpuUsage(),
            'memory' => $this->getMemoryUsage(),
            'disk' => $this->getDiskUsage(),
            'load' => $this->getLoadAverage(),
            'uptime' => $this->getUptime(),
            'network' => $this->getNetworkStats(),
            'php_fpm' => $this->getPhpFpmStats(),
            'nginx' => $this->getNginxStats(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Store metrics in database
     */
    public function storeMetrics(array $metrics): void
    {
        DB::table('monitoring_logs')->insert([
            'metric_type' => 'system',
            'metric_value' => json_encode($metrics),
            'created_at' => now(),
        ]);
    }

    /**
     * Get historical metrics
     */
    public function getHistoricalMetrics(int $hours = 24): array
    {
        return DB::table('monitoring_logs')
            ->where('metric_type', 'system')
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'timestamp' => $log->created_at,
                    'metrics' => json_decode($log->metric_value, true),
                ];
            })
            ->toArray();
    }

    /**
     * Check if any metric exceeds threshold
     */
    public function checkThresholds(array $metrics): array
    {
        $alerts = [];

        // CPU threshold: 80%
        if ($metrics['cpu'] > 80) {
            $alerts[] = [
                'level' => 'warning',
                'metric' => 'cpu',
                'value' => $metrics['cpu'],
                'threshold' => 80,
                'message' => 'CPU usage is high',
            ];
        }

        // Memory threshold: 90%
        if ($metrics['memory']['percentage'] > 90) {
            $alerts[] = [
                'level' => 'critical',
                'metric' => 'memory',
                'value' => $metrics['memory']['percentage'],
                'threshold' => 90,
                'message' => 'Memory usage is critical',
            ];
        }

        // Disk threshold: 85%
        if ($metrics['disk']['percentage'] > 85) {
            $alerts[] = [
                'level' => 'warning',
                'metric' => 'disk',
                'value' => $metrics['disk']['percentage'],
                'threshold' => 85,
                'message' => 'Disk space is running low',
            ];
        }

        // Load average threshold (per CPU core)
        $cpuCores = (int)shell_exec('nproc') ?: 1;
        if ($metrics['load']['5min'] > $cpuCores * 2) {
            $alerts[] = [
                'level' => 'warning',
                'metric' => 'load',
                'value' => $metrics['load']['5min'],
                'threshold' => $cpuCores * 2,
                'message' => 'System load is high',
            ];
        }

        return $alerts;
    }
}
