<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Domain;
use App\Services\WildcardSslService;

class SslIssueCommand extends Command
{
    protected $signature = 'ssl:issue {domain : Domain name to issue wildcard SSL for}';
    protected $description = 'Issue a wildcard SSL certificate for a single domain';

    public function handle(WildcardSslService $ssl): int
    {
        $name = $this->argument('domain');
        $domain = Domain::where('name', $name)->first();
        if (!$domain) {
            $this->error('Domain not found: '.$name);
            return self::FAILURE;
        }
        $domain->wildcard_ssl_enabled = true;
        $domain->save();
        $ssl->requestWildcardCertificate($domain);
        $this->info('Wildcard SSL issued for '.$domain->name);
        return self::SUCCESS;
    }
}
