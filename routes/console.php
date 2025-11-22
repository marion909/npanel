<?php

use App\Jobs\CollectMetricsJob;
use App\Jobs\RenewSslCertificatesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule metrics collection every 5 minutes
Schedule::job(new CollectMetricsJob())->everyFiveMinutes()->name('collect-metrics');

// SSL certificate renewal check (daily at 2 AM)
Schedule::job(new RenewSslCertificatesJob())->dailyAt('02:00')->name('renew-ssl-certificates');
