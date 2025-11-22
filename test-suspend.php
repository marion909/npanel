<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$domain = App\Models\Domain::where('domain_name', 'npanel.at')->first();

echo "Domain ID: {$domain->id}\n";
echo "Domain Status: {$domain->status}\n";
echo "Subdomains: {$domain->subdomains->count()}\n";
$domain->subdomains->each(function($s) {
    echo "  - {$s->subdomain_name} (ID: {$s->id})\n";
});

echo "\n=== Suspending domain ===\n";

$domainService = app(\App\Services\DomainService::class);
$domainService->suspendDomain($domain);

echo "Domain suspended!\n";
echo "New status: " . $domain->fresh()->status . "\n";

echo "\nPlease test:\n";
echo "  - https://npanel.at (should show suspended page)\n";
echo "  - https://www.npanel.at (should show suspended page)\n";
echo "  - https://demo.npanel.at (should show suspended page)\n";
