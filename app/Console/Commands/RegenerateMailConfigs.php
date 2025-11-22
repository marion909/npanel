<?php

namespace App\Console\Commands;

use App\Services\PostfixService;
use App\Services\DovecotService;
use Illuminate\Console\Command;
use Exception;

class RegenerateMailConfigs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:regenerate-configs {--no-reload : Do not reload services after regenerating configs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate Postfix and Dovecot configuration files';

    private PostfixService $postfixService;
    private DovecotService $dovecotService;

    /**
     * Create a new command instance.
     */
    public function __construct(PostfixService $postfixService, DovecotService $dovecotService)
    {
        parent::__construct();
        $this->postfixService = $postfixService;
        $this->dovecotService = $dovecotService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Regenerating mail server configuration files...');

        try {
            // Regenerate Postfix configs
            $this->info('Generating Postfix MySQL configuration files...');
            $this->postfixService->generateConfigs();
            $this->info('✓ Postfix configs generated');

            // Regenerate Dovecot configs
            $this->info('Generating Dovecot configuration files...');
            $this->dovecotService->generateSqlConfig();
            $this->info('✓ Dovecot configs generated');

            // Test configurations
            $this->info('Testing configurations...');
            
            if (!$this->postfixService->testConfig()) {
                $this->error('✗ Postfix configuration test failed');
                $this->warn('Check /var/log/mail.log for details');
                return 1;
            }
            $this->info('✓ Postfix configuration valid');

            if (!$this->dovecotService->testConfig()) {
                $this->error('✗ Dovecot configuration test failed');
                $this->warn('Check /var/log/dovecot.log for details');
                return 1;
            }
            $this->info('✓ Dovecot configuration valid');

            // Reload services unless --no-reload is specified
            if (!$this->option('no-reload')) {
                $this->info('Reloading mail services...');
                
                $this->postfixService->reload();
                $this->info('✓ Postfix reloaded');

                $this->dovecotService->reload();
                $this->info('✓ Dovecot reloaded');
            } else {
                $this->warn('Skipping service reload (--no-reload option)');
                $this->info('Run manually: systemctl reload postfix dovecot');
            }

            $this->newLine();
            $this->info('Mail server configuration regenerated successfully!');
            
            return 0;

        } catch (Exception $e) {
            $this->error('Failed to regenerate mail configs: ' . $e->getMessage());
            return 1;
        }
    }
}
