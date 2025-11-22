<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$domain = App\Models\Domain::where('domain_name', 'npanel.at')->first();

echo "Domain: {$domain->domain_name}\n";
echo "Current status: {$domain->status}\n\n";

$domainService = app(\App\Services\DomainService::class);

echo "=== Resuming domain ===\n";
$domainService->resumeDomain($domain);
echo "Domain resumed!\n";
echo "New status: " . $domain->fresh()->status . "\n\n";

echo "Domain should now be active again:\n";
echo "  - https://npanel.at (should show nPanel dashboard)\n";
echo "  - https://www.npanel.at (should show nPanel dashboard)\n";
echo "  - https://demo.npanel.at (should show WordPress)\n";
