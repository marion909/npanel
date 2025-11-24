<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\SystemInstallCommand::class,
        \App\Console\Commands\HetznerSyncCommand::class,
        \App\Console\Commands\HetznerZoneScanCommand::class,
        \App\Console\Commands\SslIssueCommand::class,
        \App\Console\Commands\SslRenewCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Renew SSL daily (placeholder cron)
        $schedule->command('ssl:renew')->daily();
        // Sync Hetzner records hourly
        $schedule->command('hetzner:sync')->hourly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
