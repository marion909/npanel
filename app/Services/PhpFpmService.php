<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\PhpFpmPool;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class PhpFpmService
{
    /**
     * Get available PHP versions
     */
    public function getAvailableVersions(): array
    {
        return array_keys(config('npanel.php_versions'));
    }

    /**
     * Get default PHP version
     */
    public function getDefaultVersion(): string
    {
        return config('npanel.default_php_version');
    }

    /**
     * Create PHP-FPM pool for a domain
     */
    public function createPool(Domain $domain): PhpFpmPool
    {
        $poolName = Str::slug($domain->domain_name);
        $socketPath = config('npanel.php_fpm_socket_dir') . '/php' . $domain->php_version . '-fpm-' . $poolName . '.sock';

        // Create or retrieve existing pool record
        $pool = PhpFpmPool::firstOrCreate(
            [
                'pool_name' => $poolName,
            ],
            [
                'domain_id' => $domain->id,
                'php_version' => $domain->php_version,
                'socket_path' => $socketPath,
                'pm_mode' => 'dynamic',
                'pm_max_children' => 5,
                'pm_start_servers' => 2,
                'pm_min_spare_servers' => 1,
                'pm_max_spare_servers' => 3,
                'memory_limit' => '128M',
                'max_execution_time' => 300,
            ]
        );

        // Generate and write pool configuration
        $configContent = $this->generatePoolConfig($pool, $domain);
        $configPath = $this->getPoolConfigPath($pool);

        File::put($configPath, $configContent);

        return $pool;
    }

    /**
     * Generate PHP-FPM pool configuration
     */
    public function generatePoolConfig(PhpFpmPool $pool, Domain $domain): string
    {
        return View::make('templates/php-fpm/pool', [
            'pool' => $pool,
            'domain' => $domain,
        ])->render();
    }

    /**
     * Get pool configuration file path
     */
    protected function getPoolConfigPath(PhpFpmPool $pool): string
    {
        $poolDir = str_replace('{version}', $pool->php_version, config('npanel.php_fpm_pool_dir'));
        return $poolDir . '/' . $pool->pool_name . '.conf';
    }

    /**
     * Test PHP-FPM configuration for specific version
     */
    public function testConfig(string $phpVersion): array
    {
        $phpFpmBinary = config('npanel.php_versions.' . $phpVersion);

        if (!$phpFpmBinary) {
            throw new \Exception("PHP version {$phpVersion} is not configured");
        }

        $command = $phpFpmBinary . ' -t 2>&1';
        $output = [];
        $returnCode = 0;

        exec($command, $output, $returnCode);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output),
            'return_code' => $returnCode,
        ];
    }

    /**
     * Reload PHP-FPM service for specific version
     */
    public function reload(string $phpVersion): array
    {
        // First test the configuration
        $testResult = $this->testConfig($phpVersion);

        if (!$testResult['success']) {
            throw new \Exception("PHP-FPM {$phpVersion} configuration test failed: " . $testResult['output']);
        }

        $command = str_replace('{version}', $phpVersion, config('npanel.php_fpm_reload_command'));
        $output = [];
        $returnCode = 0;

        exec($command . ' 2>&1', $output, $returnCode);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output),
            'return_code' => $returnCode,
        ];
    }

    /**
     * Update pool configuration
     */
    public function updatePool(PhpFpmPool $pool, Domain $domain): void
    {
        $configContent = $this->generatePoolConfig($pool, $domain);
        $configPath = $this->getPoolConfigPath($pool);

        // Backup existing config
        if (File::exists($configPath)) {
            $backupPath = $configPath . '.backup.' . now()->format('YmdHis');
            File::copy($configPath, $backupPath);
        }

        File::put($configPath, $configContent);
    }

    /**
     * Remove pool configuration
     */
    public function removePool(PhpFpmPool $pool): bool
    {
        $configPath = $this->getPoolConfigPath($pool);

        if (File::exists($configPath)) {
            return File::delete($configPath);
        }

        return true;
    }

    /**
     * Check if PHP version is installed
     */
    public function isVersionInstalled(string $version): bool
    {
        $binary = config('npanel.php_versions.' . $version);

        if (!$binary) {
            return false;
        }

        return File::exists($binary);
    }

    /**
     * Get PHP-FPM service status
     */
    public function getServiceStatus(string $phpVersion): array
    {
        $serviceName = 'php' . $phpVersion . '-fpm';
        $command = "systemctl is-active {$serviceName} 2>&1";
        $output = [];
        $returnCode = 0;

        exec($command, $output, $returnCode);

        return [
            'running' => $returnCode === 0 && trim($output[0] ?? '') === 'active',
            'status' => trim($output[0] ?? 'unknown'),
        ];
    }
}
