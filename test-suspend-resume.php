<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$domain = App\Models\Domain::where('domain_name', 'npanel.at')->first();

echo "Domain: {$domain->domain_name}\n";
echo "Current status: {$domain->status}\n\n";

$domainService = app(\App\Services\DomainService::class);

if ($domain->status === 'suspended') {
    echo "=== Resuming domain ===\n";
    $domainService->resumeDomain($domain);
    echo "Domain resumed!\n\n";
    sleep(2);
    $domain->refresh();
}

echo "=== Suspending domain ===\n";
$domainService->suspendDomain($domain);
echo "Domain suspended!\n";
echo "New status: " . $domain->fresh()->status . "\n\n";

echo "Please test:\n";
echo "  - https://npanel.at (should show suspended page)\n";
echo "  - https://www.npanel.at (should show suspended page)\n";
echo "  - https://demo.npanel.at (should show suspended page)\n";
echo "  - https://demo2.npanel.at (should show suspended page)\n";
echo "  - https://demo3.npanel.at (should show suspended page)\n";
