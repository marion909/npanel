<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Domain;
use App\Services\HetznerDnsService;

class HetznerZoneScanCommand extends Command
{
    protected $signature = 'hetzner:zone-scan';
    protected $description = 'Populate missing hetzner_zone_id values for domains';

    public function handle(HetznerDnsService $dns): int
    {
        if (!config('services.hetzner_dns.api_token')) {
            $this->error('No Hetzner API token configured.');
            return self::FAILURE;
        }
        $count = 0;
        Domain::whereNull('hetzner_zone_id')->chunk(100, function ($chunk) use ($dns, &$count) {
            foreach ($chunk as $domain) {
                $zoneId = $dns->findZoneIdForDomain($domain->name);
                if ($zoneId) {
                    $domain->hetzner_zone_id = $zoneId;
                    $domain->save();
                    $count++;
                    $this->line('Updated zone id for '.$domain->name);
                }
            }
        });
        $this->info("Zone scan complete: $count domains updated.");
        return self::SUCCESS;
    }
}
