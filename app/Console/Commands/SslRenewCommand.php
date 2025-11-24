<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Domain;
use App\Services\WildcardSslService;

class SslRenewCommand extends Command
{
    protected $signature = 'ssl:renew {--domain= : Renew only this domain}';
    protected $description = 'Renew wildcard SSL certificates (all or one)';

    public function handle(WildcardSslService $ssl): int
    {
        $filter = $this->option('domain');
        $query = Domain::query()->where('wildcard_ssl_enabled', true);
        if ($filter) {
            $query->where('name', $filter);
        }
        $count = 0;
        $query->chunk(50, function ($chunk) use ($ssl, &$count) {
            foreach ($chunk as $domain) {
                $ssl->renewWildcardCertificate($domain);
                $this->line('Renewed: '.$domain->name);
                $count++;
            }
        });
        $this->info("Renew complete: $count certificates processed.");
        return self::SUCCESS;
    }
}
