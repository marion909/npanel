<?php

namespace App\Console\Commands;

use App\Jobs\InstallMailServerJob;
use Illuminate\Console\Command;

class InstallMailServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'npanel:install-mail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install and configure mail server (Postfix, Dovecot, OpenDKIM, Roundcube)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching mail server installation job...');
        
        InstallMailServerJob::dispatch();
        
        $this->info('Mail server installation job dispatched.');
        $this->info('Monitor progress with: php artisan queue:work');
        
        return Command::SUCCESS;
    }
}
