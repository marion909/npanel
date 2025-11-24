<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Domain;
use App\Services\HetznerDnsService;

class HetznerSyncCommand extends Command
{
    protected $signature = 'hetzner:sync';
    protected $description = 'Ensure required DNS records (A + verification TXT) exist for all domains';

    public function handle(HetznerDnsService $dns): int
    {
        if (!env('HETZNER_DNS_API_TOKEN')) {
            $this->error('No Hetzner API token configured.');
            return self::FAILURE;
        }
        $ip = env('APP_SERVER_IPV4', '127.0.0.1');
        Domain::whereNotNull('hetzner_zone_id')->chunk(50, function ($chunk) use ($dns, $ip) {
            foreach ($chunk as $domain) {
                $dns->ensureARecord($domain, $ip);
                $dns->createVerificationTxtRecord($domain);
                $this->line('Synced: '.$domain->name);
            }
        });
        $this->info('Hetzner sync completed.');
        return self::SUCCESS;
    }
}
