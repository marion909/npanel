<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SystemInstallCommand extends Command
{
    protected $signature = 'system:install {--force : Overwrite existing directories}';
    protected $description = 'Prepare base directories (document roots, nginx folders)';

    public function handle(): int
    {
        $docBase = rtrim(env('DOCUMENT_ROOT_BASE', '/var/www'), '/');
        $nginxAvail = env('NGINX_SITES_AVAILABLE', '/etc/nginx/sites-available');
        $nginxEnabled = env('NGINX_SITES_ENABLED', '/etc/nginx/sites-enabled');

        foreach ([$docBase, $nginxAvail, $nginxEnabled] as $dir) {
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
                $this->info("Created: $dir");
            } elseif ($this->option('force')) {
                $this->info("Exists (force ignored): $dir");
            } else {
                $this->line("Exists: $dir");
            }
        }

        $this->info('System install base setup complete.');
        return self::SUCCESS;
    }
}
