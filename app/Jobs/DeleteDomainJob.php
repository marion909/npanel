<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Services\DatabaseService;
use App\Services\NginxService;
use App\Services\PhpFpmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DeleteDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1; // Don't retry deletion

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $domainName,
        public string $documentRoot,
        public ?string $phpVersion,
        public ?string $phpFpmPool,
        public array $databases, // Array of ['name' => ..., 'user' => ...]
        public array $subdomainIds
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        NginxService $nginxService,
        PhpFpmService $phpFpmService,
        DatabaseService $databaseService
    ): void {
        try {
            Log::info("Starting domain deletion: {$this->domainName}");

            // 1. Delete all MySQL databases associated with this domain
            $mysqlRoot = app(\App\Services\MySQLRootConnection::class);
            foreach ($this->databases as $dbInfo) {
                try {
                    // Drop MySQL database
                    if ($mysqlRoot->databaseExists($dbInfo['name'])) {
                        $mysqlRoot->dropDatabase($dbInfo['name']);
                        Log::info("Dropped MySQL database: {$dbInfo['name']}");
                    }
                    
                    // Drop MySQL user
                    if ($mysqlRoot->userExists($dbInfo['user'])) {
                        $mysqlRoot->dropUser($dbInfo['user']);
                        Log::info("Dropped MySQL user: {$dbInfo['user']}");
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to delete database {$dbInfo['name']}: " . $e->getMessage());
                }
            }

            // 2. Remove Nginx configurations (main domain + subdomains)
            $configPath = config('npanel.nginx_sites_available') . '/' . $this->domainName . '.conf';
            $enabledPath = config('npanel.nginx_sites_enabled') . '/' . $this->domainName . '.conf';
            
            if (File::exists($enabledPath)) {
                File::delete($enabledPath);
                Log::info("Deleted Nginx enabled symlink: {$enabledPath}");
            }
            
            if (File::exists($configPath)) {
                File::delete($configPath);
                Log::info("Deleted Nginx config: {$configPath}");
            }

            // 3. Remove PHP-FPM pool configuration
            if ($this->phpVersion && $this->phpFpmPool) {
                $poolConfigPath = "/etc/php/{$this->phpVersion}/fpm/pool.d/{$this->phpFpmPool}.conf";
                if (File::exists($poolConfigPath)) {
                    File::delete($poolConfigPath);
                    Log::info("Deleted PHP-FPM pool: {$poolConfigPath}");
                }
            }

            // 4. Delete document root directory (excluding SSL certs)
            if (File::exists($this->documentRoot)) {
                // Get parent directory (domain directory)
                $domainDir = dirname($this->documentRoot);
                
                if (File::isDirectory($domainDir) && str_contains($domainDir, '/domains/')) {
                    File::deleteDirectory($domainDir);
                    Log::info("Deleted domain directory: {$domainDir}");
                }
            }

            // 5. Reload services
            try {
                // Test Nginx config before reload
                $result = Process::run('nginx -t');
                if ($result->successful()) {
                    Process::run('systemctl reload nginx');
                    Log::info("Nginx reloaded successfully");
                } else {
                    Log::error("Nginx config test failed: " . $result->errorOutput());
                }

                // Reload PHP-FPM if pool was removed
                if ($this->phpVersion) {
                    Process::run("systemctl reload php{$this->phpVersion}-fpm");
                    Log::info("PHP-FPM {$this->phpVersion} reloaded");
                }
            } catch (\Exception $e) {
                Log::error("Failed to reload services: " . $e->getMessage());
            }

            Log::info("Domain deletion completed: {$this->domainName}");

        } catch (\Exception $e) {
            Log::error("Domain deletion job failed for {$this->domainName}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
